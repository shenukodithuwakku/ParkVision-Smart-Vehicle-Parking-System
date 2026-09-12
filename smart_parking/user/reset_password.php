<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!empty($_SESSION['user_id'])) redirect(BASE_URL . 'user/dashboard.php');

$token = clean_input($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$done = false;
$tokenValid = false;
$resetEmail = '';

if ($token !== '') {
    try {
        $stmt = db()->prepare('SELECT email, expires_at, used FROM password_resets WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if ($row && !$row['used'] && strtotime($row['expires_at']) > time()) {
            $tokenValid = true;
            $resetEmail = $row['email'];
        }
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    csrf_verify();
    $pw  = (string)($_POST['password'] ?? '');
    $cpw = (string)($_POST['confirm_password'] ?? '');
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $cpw) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $hash = password_hash($pw, PASSWORD_BCRYPT);
        db()->prepare('UPDATE users SET password_hash = ?, remember_token = NULL WHERE email = ?')->execute([$hash, $resetEmail]);
        db()->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')->execute([$token]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reset Password | Smart Parking</title>
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
.inp-wrap{position:relative;margin-bottom:1rem;}
.inp-wrap i.ico{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:#9CA3AF;pointer-events:none;}
.inp-wrap input{width:100%;padding:.72rem 1rem .72rem 2.5rem;border:1.5px solid #E5E7EB;border-radius:.75rem;font-size:.95rem;background:#F9FAFB;outline:none;transition:border-color .2s,box-shadow .2s;}
.inp-wrap input:focus{border-color:#0D9488;box-shadow:0 0 0 3px rgba(13,148,136,.15);background:#fff;}
.btn-a{width:100%;padding:.76rem;border:none;border-radius:.75rem;background:linear-gradient(135deg,#0D9488,#0369A1);color:#fff;font-weight:700;font-size:.97rem;cursor:pointer;box-shadow:0 4px 14px -3px rgba(13,148,136,.5);}
.err-box{background:#FEF2F2;border:1.5px solid #FECACA;border-radius:.75rem;padding:.75rem;color:#991B1B;font-size:.88rem;margin-bottom:1rem;}
.err-box ul{margin:0;padding-left:1rem;}
.success-box{text-align:center;padding:1.5rem 0;}
.success-box i{font-size:3rem;color:#10B981;display:block;margin-bottom:1rem;}
.back-link{text-align:center;margin-top:1.2rem;font-size:.88rem;color:#6B7280;}
.back-link a{color:#0D9488;font-weight:700;text-decoration:none;}
.invalid-box{text-align:center;padding:1rem 0;color:#EF4444;}
.pw-strength{height:4px;border-radius:2px;margin-top:.4rem;background:#E5E7EB;overflow:hidden;}
.pw-strength-bar{height:100%;width:0;border-radius:2px;transition:width .3s ease,background-color .3s ease;}
</style>
</head>
<body>
<div class="auth-center">
  <div class="auth-card">
    <div class="logo"><i class="bi bi-shield-lock-fill"></i></div>
    <h2>New Password</h2>
    <p class="sub">Choose a strong new password for your account.</p>

    <?php if ($done): ?>
      <div class="success-box">
        <i class="bi bi-check-circle-fill"></i>
        <h5 style="font-family:'Sora',sans-serif;font-weight:800;">Password Updated!</h5>
        <p style="color:#64748B;font-size:.9rem;">Your password has been changed successfully.</p>
        <a href="<?= BASE_URL ?>user/login.php" style="display:inline-block;padding:.72rem 2rem;border-radius:.75rem;background:linear-gradient(135deg,#0D9488,#0369A1);color:#fff;font-weight:700;text-decoration:none;margin-top:.5rem;">Sign In Now</a>
      </div>
    <?php elseif (!$tokenValid): ?>
      <div class="invalid-box">
        <i class="bi bi-x-circle-fill" style="font-size:2.5rem;"></i>
        <h5 style="font-family:'Sora',sans-serif;font-weight:700;margin-top:.75rem;">Invalid or Expired Link</h5>
        <p style="color:#64748B;font-size:.9rem;">This reset link has expired or already been used.</p>
      </div>
      <div class="back-link"><a href="<?= BASE_URL ?>user/forgot_password.php">Request a new link</a></div>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="err-box"><ul><?php foreach ($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="lbl">New Password</label>
        <div class="inp-wrap">
          <i class="bi bi-lock ico"></i>
          <input type="password" name="password" id="pwField" placeholder="Min. 8 characters" required minlength="8" oninput="checkPw(this.value)">
        </div>
        <div class="pw-strength" style="margin-top:-0.7rem;margin-bottom:.75rem;"><div class="pw-strength-bar" id="pwBar"></div></div>
        <label class="lbl">Confirm Password</label>
        <div class="inp-wrap">
          <i class="bi bi-lock-fill ico"></i>
          <input type="password" name="confirm_password" placeholder="Repeat new password" required minlength="8">
        </div>
        <button type="submit" class="btn-a"><i class="bi bi-check2-circle me-2"></i>Update Password</button>
      </form>
    <?php endif; ?>
    <?php if (!$done): ?>
      <div class="back-link"><a href="<?= BASE_URL ?>user/login.php">← Back to login</a></div>
    <?php endif; ?>
  </div>
</div>
<script>
function checkPw(v) {
  let s=0;
  if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
  const c=['#EF4444','#F59E0B','#10B981','#0D9488'];
  const b=document.getElementById('pwBar');
  b.style.width=(s*25)+'%';b.style.backgroundColor=c[s-1]||'#E5E7EB';
}
</script>
</body>
</html>
