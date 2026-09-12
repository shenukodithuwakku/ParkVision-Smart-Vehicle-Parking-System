<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!empty($_SESSION['admin_id'])) redirect(BASE_URL . 'admin/dashboard.php');
$errors = []; $userOld = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $userOld  = clean_input($_POST['username'] ?? '');
    $password = (string)($_POST['password']  ?? '');
    if ($userOld === '' || $password === '') {
        $errors[] = 'Please enter your username and password.';
    } else {
        $stmt = db()->prepare('SELECT admin_id,full_name,username,password_hash,role,status FROM admins WHERE username=? OR email=?');
        $stmt->execute([$userOld,$userOld]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            $errors[] = 'Invalid username or password.';
        } elseif ($admin['status'] !== 'active') {
            $errors[] = 'This admin account is disabled.';
        } else {
            regenerate_session();
            $_SESSION['admin_id']   = (int)$admin['admin_id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            db()->prepare('UPDATE admins SET last_login_at=NOW() WHERE admin_id=?')->execute([(int)$admin['admin_id']]);
            log_activity('admin',(int)$admin['admin_id'],'login','Admin logged in');
            redirect(BASE_URL.'admin/dashboard.php');
        }
    }
}
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Login — ParkVision</title>
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

      <div class="auth-badge"><i class="bi bi-shield-lock-fill"></i>&nbsp;ADMIN ACCESS ONLY</div>
      <h1 class="auth-title">Welcome <span>Back</span></h1>
      <p class="auth-sub">Sign in to the admin control panel</p>

      <?php if($errors): ?><div class="auth-err"><ul><?php foreach($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php foreach($flashes as $f_): ?><div class="auth-ok"><?= e($f_['message']) ?></div><?php endforeach; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="af"><i class="bi bi-person"></i><input type="text" name="username" value="<?= e($userOld) ?>" placeholder="Username or Email" required autocomplete="username"></div>
        <div class="af"><i class="bi bi-lock"></i><input type="password" name="password" id="pw" placeholder="Password" required autocomplete="current-password"><button type="button" class="eye" onclick="tg('pw','ei')"><i class="bi bi-eye" id="ei"></i></button></div>
        <div class="af-meta">
          <label class="af-check"><input type="checkbox" name="remember_me" checked><span>Keep me signed in</span></label>
          <a href="<?= BASE_URL ?>user/login.php" class="af-link" style="color:#9aabcc;font-size:.8rem;">← User portal</a>
        </div>
        <button type="submit" class="btn-auth-primary"><i class="bi bi-shield-check"></i>&nbsp;Sign In as Admin</button>
      </form>

      <div class="auth-or"><hr><span>OR</span><hr></div>
      <a href="<?= BASE_URL ?>user/login.php" style="text-decoration:none;">
        <button class="btn-auth-ghost"><i class="bi bi-person"></i> Back to User Login</button>
      </a>
    </div>
  </div>
</div>
<p class="auth-copy">&copy; <?= date('Y') ?> ParkVision. All rights reserved.</p>
<script>function tg(f,i){const el=document.getElementById(f),ic=document.getElementById(i);el.type=el.type==='password'?'text':'password';ic.className=el.type==='password'?'bi bi-eye':'bi bi-eye-slash';}</script>
</body>
</html>
