<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
if (!empty($_SESSION['user_id'])) redirect(BASE_URL . 'user/dashboard.php');
$errors = []; $emailOld = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $emailOld = clean_input($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $rememberMe = !empty($_POST['remember_me']);
    if ($emailOld === '' || $password === '') { $errors[] = 'Please enter your email and password.'; }
    else {
        $stmt = db()->prepare('SELECT user_id,full_name,email,password_hash,status FROM users WHERE email=?');
        $stmt->execute([$emailOld]); $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) { $errors[] = 'Incorrect email or password.'; }
        elseif ($user['status'] !== 'active') { $errors[] = 'Your account has been blocked.'; }
        else {
            regenerate_session();
            $_SESSION['user_id'] = (int)$user['user_id']; $_SESSION['user_name'] = $user['full_name']; $_SESSION['user_email'] = $user['email'];
            if ($rememberMe) { $tok = bin2hex(random_bytes(32)); db()->prepare('UPDATE users SET remember_token=? WHERE user_id=?')->execute([$tok,(int)$user['user_id']]); setcookie(REMEMBER_COOKIE,$tok,time()+30*86400,'/','',(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'),true); }
            log_activity('user',(int)$user['user_id'],'login','User logged in');
            redirect(BASE_URL.'user/dashboard.php');
        }
    }
}
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login — ParkVision</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css">
</head>
<body class="auth-body">
<div class="auth-particles"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
<div class="auth-page">

  <div class="auth-card">
    <div class="auth-card-inner">
      <div class="card-shine"></div>
      <div class="card-dots"></div>
      <div class="card-orb"></div>

      <div class="card-logo-row">
        <img class="card-logo-icon" src="<?= BASE_URL ?>assets/images/logo_icon.png" alt="ParkVision">
        <div class="card-logo-text"><span class="cl-park">Park</span><span class="cl-vision">Vision</span></div>
      </div>

      <h1 class="auth-title">Welcome <span>Back</span></h1>
      <p class="auth-sub">Sign in to continue to your account</p>

      <?php if($errors): ?><div class="auth-err"><ul><?php foreach($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php foreach($flashes as $f_): ?><div class="auth-ok"><?= e($f_['message']) ?></div><?php endforeach; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="af"><i class="bi bi-envelope"></i><input type="email" name="email" value="<?= e($emailOld) ?>" placeholder="Email Address" required autocomplete="email"></div>
        <div class="af"><i class="bi bi-lock"></i><input type="password" name="password" id="pw" placeholder="Password" required autocomplete="current-password"><button type="button" class="eye" onclick="tg('pw','ei')"><i class="bi bi-eye" id="ei"></i></button></div>
        <div class="af-meta">
          <label class="af-check"><input type="checkbox" name="remember_me" checked><span>Remember me</span></label>
          <a href="<?= BASE_URL ?>user/forgot_password.php" class="af-link">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-auth-primary">Login &nbsp;→</button>
      </form>

      <div class="auth-or"><hr><span>OR</span><hr></div>
      <a href="<?= BASE_URL ?>admin/login.php" style="text-decoration:none;"><button class="btn-auth-ghost"><i class="bi bi-shield-lock-fill"></i> Login as Admin</button></a>
      <p class="auth-foot">Don't have an account? <a href="<?= BASE_URL ?>user/register.php">Register Now</a></p>
    </div>
  </div>
</div>
<p class="auth-copy">&copy; <?= date('Y') ?> ParkVision. All rights reserved.</p>
<script>function tg(f,i){const el=document.getElementById(f),ic=document.getElementById(i);el.type=el.type==='password'?'text':'password';ic.className=el.type==='password'?'bi bi-eye':'bi bi-eye-slash';}</script>
</body></html>
