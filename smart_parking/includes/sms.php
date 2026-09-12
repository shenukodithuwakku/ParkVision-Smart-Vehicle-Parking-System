<?php
/**
 * SMS notifications via Dialog eSMS (business.dialog.lk e-SMS).
 *
 * IMPORTANT: Dialog does not publish one universal public API — each
 * business account gets its own "URL Message Key" plus an exact request
 * format from the eSMS control panel (Settings → API/Developer section)
 * after signing up at https://esms.dialog.lk. The request below uses the
 * most common documented pattern (GET request, apiKey + numbers + message
 * query params). If your account's dashboard shows a different endpoint
 * or parameter names, update DIALOG_SMS_ENDPOINT / the query params in
 * send_sms_dialog() below to match exactly what Dialog gave you.
 */

/**
 * @return array{success:bool, message:string}
 */
function send_sms_dialog(string $phone, string $message): array
{
    if (!SMS_ENABLED) {
        return ['success' => false, 'message' => 'SMS sending is not configured yet (SMS_ENABLED is false in config.php).'];
    }

    $digits = preg_replace('/\D/', '', $phone);
    if ($digits === '') {
        return ['success' => false, 'message' => 'Invalid phone number.'];
    }
    if (str_starts_with($digits, '0')) {
        $digits = '94' . substr($digits, 1);       // local 0-prefixed -> country code 94
    } elseif (strlen($digits) === 9) {
        $digits = '94' . $digits;                  // e.g. 771234567 -> 94771234567
    }
    if (strlen($digits) < 11) {
        return ['success' => false, 'message' => 'Invalid phone number after normalisation.'];
    }

    $params = [
        'q'        => DIALOG_SMS_API_KEY,   // "URL Message Key" from your eSMS account
        'destination' => $digits,
        'message'  => $message,
        'mask'     => DIALOG_SMS_MASK,      // approved sender ID/mask on your eSMS account
    ];
    $url = DIALOG_SMS_ENDPOINT . '?' . http_build_query($params);

    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['success' => false, 'message' => "cURL error: $error"];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['success' => false, 'message' => "Dialog eSMS returned HTTP $httpCode: " . substr((string) $response, 0, 200)];
        }
        return ['success' => true, 'message' => 'SMS sent. Provider response: ' . substr((string) $response, 0, 200)];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/** Best-effort helper used by api/entry.php and api/exit.php — never throws,
 *  logs failures instead of interrupting the entry/exit flow. */
function notify_sms_best_effort(string $phone, string $message): void
{
    if (!SMS_ENABLED || $phone === '') {
        return;
    }
    try {
        $result = send_sms_dialog($phone, $message);
        if (!$result['success']) {
            error_log('SMS notify failed: ' . $result['message']);
        }
    } catch (Throwable $e) {
        error_log('SMS notify exception: ' . $e->getMessage());
    }
}
