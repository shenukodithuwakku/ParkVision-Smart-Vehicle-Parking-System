<?php
/**
 * Tiny dependency-free SMTP client (no Composer / PHPMailer needed).
 * Good enough for transactional "here's your QR" emails via Gmail SMTP
 * or any standard SMTP provider that supports AUTH LOGIN + STARTTLS/SSL.
 */

class SimpleSmtpMailer
{
    private string $host;
    private int $port;
    private string $secure; // 'tls' | 'ssl' | ''
    private string $username;
    private string $password;
    private $sock;

    public function __construct(string $host, int $port, string $secure, string $username, string $password)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->secure   = $secure;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function send(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $htmlBody): array
    {
        try {
            $this->connect();
            $this->hello();
            if ($this->secure === 'tls') {
                $this->command("STARTTLS", 220);
                if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS handshake failed.');
                }
                $this->hello(); // re-issue EHLO after STARTTLS as required
            }
            $this->auth();

            $this->command("MAIL FROM:<{$fromEmail}>", 250);
            $this->command("RCPT TO:<{$toEmail}>", 250);
            $this->command("DATA", 354);

            $boundary = 'bnd_' . bin2hex(random_bytes(8));
            $headers  = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->encodeHeader($fromName) . " <{$fromEmail}>",
                'To: ' . $this->encodeHeader($toName) . " <{$toEmail}>",
                'Subject: ' . $this->encodeHeader($subject),
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->host . '>',
            ];
            $raw = implode("\r\n", $headers) . "\r\n\r\n" . $this->stuffDots($htmlBody) . "\r\n.";
            $this->rawWrite($raw);
            $this->readResponse(250);

            $this->command("QUIT", 221);
            $this->close();

            return ['success' => true, 'message' => 'Email sent.'];
        } catch (Throwable $e) {
            $this->close();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function connect(): void
    {
        $prefix = ($this->secure === 'ssl') ? 'ssl://' : '';
        $this->sock = @stream_socket_client(
            "{$prefix}{$this->host}:{$this->port}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT
        );
        if (!$this->sock) {
            throw new RuntimeException("Could not connect to SMTP server: $errstr ($errno)");
        }
        $this->readResponse(220);
    }

    private function hello(): void
    {
        $this->command("EHLO " . (gethostname() ?: 'localhost'), 250);
    }

    private function auth(): void
    {
        $this->command("AUTH LOGIN", 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    private function command(string $cmd, int $expectCode): string
    {
        $this->rawWrite($cmd);
        return $this->readResponse($expectCode);
    }

    private function rawWrite(string $data): void
    {
        fwrite($this->sock, $data . "\r\n");
    }

    private function readResponse(int $expectCode): string
    {
        $response = '';
        while ($line = fgets($this->sock, 515)) {
            $response .= $line;
            // Multi-line replies have a "-" after the code (e.g. "250-"); the
            // final line uses a space ("250 ").
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectCode) {
            throw new RuntimeException("SMTP error (expected {$expectCode}): " . trim($response));
        }
        return $response;
    }

    private function stuffDots(string $body): string
    {
        // RFC 5321 dot-stuffing: lines starting with "." must be escaped.
        return preg_replace('/^\./m', '..', $body);
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function close(): void
    {
        if (is_resource($this->sock)) {
            fclose($this->sock);
        }
    }
}

/**
 * Send the "here's your parking QR" email to a vehicle owner.
 * Returns ['success' => bool, 'message' => string]. Never throws —
 * callers should treat a failure here as non-fatal (the entry/booking
 * itself already succeeded).
 */
function send_qr_email(string $toEmail, string $toName, string $plate, string $slotLabel, string $shareUrl): array
{
    if (!SMTP_ENABLED) {
        return ['success' => false, 'message' => 'Email sending is not configured yet (SMTP_ENABLED is false in config.php).'];
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    $safePlate = htmlspecialchars($plate, ENT_QUOTES, 'UTF-8');
    $safeSlot  = htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8');
    $safeUrl   = htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8');
    $safeName  = htmlspecialchars($toName ?: 'there', ENT_QUOTES, 'UTF-8');
    $appName   = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;color:#1f2937;">
      <h2 style="color:#2563eb;margin-bottom:4px;">{$appName}</h2>
      <p>Hi {$safeName},</p>
      <p>Your vehicle <strong>{$safePlate}</strong> has been parked in slot <strong>{$safeSlot}</strong>.</p>
      <p>Tap the button below any time to view / save your QR code — it stays the same every time this vehicle parks here:</p>
      <p style="text-align:center;margin:24px 0;">
        <a href="{$safeUrl}" style="background:#2563eb;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:bold;display:inline-block;">View My QR Code</a>
      </p>
      <p style="font-size:12px;color:#6b7280;">If the button doesn't work, copy this link: {$safeUrl}</p>
      <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
      <p style="font-size:12px;color:#9ca3af;">This is an automated message from {$appName}.</p>
    </div>
    HTML;

    $mailer = new SimpleSmtpMailer(SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USERNAME, SMTP_PASSWORD);
    return $mailer->send(SMTP_FROM_EMAIL, SMTP_FROM_NAME, $toEmail, $toName, "Your {$appName} Parking QR Code", $html);
}
