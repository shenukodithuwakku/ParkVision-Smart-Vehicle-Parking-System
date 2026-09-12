<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!empty($_SESSION['user_id'])) redirect(BASE_URL . 'user/dashboard.php');

$done = false;
$resetLink = null;
$errors = [];
$emailOld = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $emailOld = clean_input($_POST['email'] ?? '');
    if (!is_valid_email($emailOld)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND status = "active"');
        $stmt->execute([$emailOld]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            // Ensure table exists (silently)
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(120) NOT NULL, token VARCHAR(80) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_pr_token (token))");
                $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$emailOld]);
                $pdo->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)')->execute([$emailOld, $token, $expires]);
                $resetLink = BASE_URL . 'user/reset_password.php?token=' . $token;
            } catch (Exception $e) { /* silent */ }
        }
        $done = true; // always show success to prevent email enumeration
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forgot Password | Smart Parking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
<style>
html,body{height:100%;margin:0;background:linear-gradient(145deg,#0B1326 0%,#0D3B35 50%,#0B2A40 100%);}
.auth-center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
.auth-card{background:#fff;border-radius:1.25rem;padding:2.5rem;width:100%;max-width:420px;box-shadow:0 25px 60px rgba(0,0,0,.35);}
.auth-card .logo{width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#0D9488,#0EA5E9);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;}
.auth-card .logo i{font-size:1.7rem;color:#fff;}
.auth-card h2{font-family:'Sora',sans-serif;font-weight:800;text-align:center;margin-bottom:.3rem;letter-spacing:-.02em;}
.auth-card p.sub{color:#64748B;text-align:center;font-size:.9rem;margin-bottom:1.8rem;}
.lbl{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;margin-bottom:.35rem;display:block;}
.inp-wrap{position:relative;}
.inp-wrap i{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:#9CA3AF;}
.inp-wrap input{width:100%;padding:.72rem 1rem .72rem 2.5rem;border:1.5px solid #E5E7EB;border-radius:.75rem;font-size:.95rem;background:#F9FAFB;outline:none;transition:border-color .2s,box-shadow .2s;}
.inp-wrap input:focus{border-color:#0D9488;box-shadow:0 0 0 3px rgba(13,148,136,.15);background:#fff;}
.btn-a{width:100%;padding:.76rem;border:none;border-radius:.75rem;background:linear-gradient(135deg,#0D9488,#0369A1);color:#fff;font-weight:700;font-size:.97rem;cursor:pointer;margin-top:1rem;box-shadow:0 4px 14px -3px rgba(13,148,136,.5);}
.success-box{background:#ECFDF5;border:1.5px solid #6EE7B7;border-radius:.75rem;padding:1rem;color:#065F46;font-size:.88rem;margin-bottom:1rem;}
.reset-link-box{background:#F0FDF4;border:1.5px dashed #0D9488;border-radius:.75rem;padding:.9rem;margin-top:.75rem;word-break:break-all;font-size:.82rem;color:#0D9488;}
.err-box{background:#FEF2F2;border:1.5px solid #FECACA;border-radius:.75rem;padding:.75rem;color:#991B1B;font-size:.88rem;margin-bottom:1rem;}
.back-link{text-align:center;margin-top:1.2rem;font-size:.88rem;color:#6B7280;}
.back-link a{color:#0D9488;font-weight:700;text-decoration:none;}
</style>
</head>
<body>
<div class="auth-center">
  <div class="auth-card">
    <div class="logo"><i class="bi bi-key-fill"></i></div>
    <h2>Reset Password</h2>
    <p class="sub">Enter your email and we'll generate a reset link.</p>

    <?php if ($errors): ?>
      <div class="err-box"><?= e($errors[0]) ?></div>
    <?php endif; ?>

    <?php if ($done): ?>
      <div class="success-box">
        <i class="bi bi-check-circle me-1"></i>
        If that email is registered, a reset link has been generated.
        <?php if ($resetLink): ?>
          <p class="mb-1 mt-2"><strong>Demo mode — copy this link:</strong></p>
          <div class="reset-link-box"><?= e($resetLink) ?></div>
          <p class="mb-0 mt-2" style="font-size:.78rem;color:#6B7280;">In production this would be emailed. Valid for 1 hour.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <label class="lbl">Email Address</label>
        <div class="inp-wrap" style="margin-bottom:.75rem;">
          <i class="bi bi-envelope"></i>
          <input type="email" name="email" value="<?= e($emailOld) ?>" placeholder="you@example.com" required autocomplete="email">
        </div>
        <button type="submit" class="btn-a"><i class="bi bi-send me-2"></i>Send Reset Link</button>
      </form>
    <?php endif; ?>

    <div class="back-link"><a href="<?= BASE_URL ?>user/login.php">← Back to login</a></div>
  </div>
</div>
</body>
</html>
