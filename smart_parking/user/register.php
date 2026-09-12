<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
if (!empty($_SESSION['user_id'])) redirect(BASE_URL . 'user/dashboard.php');
$errors = []; $old = ['full_name'=>'','email'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old['full_name'] = clean_input($_POST['full_name'] ?? '');
    $old['email']     = clean_input($_POST['email']     ?? '');
    $password         = (string)($_POST['password']         ?? '');
    $confirm          = (string)($_POST['confirm_password'] ?? '');
    if (strlen($old['full_name']) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (!is_valid_email($old['email']))  $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8)           $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)          $errors[] = 'Passwords do not match.';
    if (empty($errors)) { $c = db()->prepare('SELECT user_id FROM users WHERE email=?'); $c->execute([$old['email']]); if ($c->fetch()) $errors[] = 'An account with this email already exists.'; }
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        db()->prepare('INSERT INTO users (full_name,email,phone,password_hash) VALUES (?,?,?,?)')->execute([$old['full_name'],$old['email'],'',$hash]);
        log_activity('user',(int)db()->lastInsertId(),'register','New account created');
        set_flash('success','Account created! Please sign in.');
        redirect(BASE_URL.'user/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Register — ParkVision</title>
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

      <h1 class="auth-title">Create <span>Account</span></h1>
      <p class="auth-sub">Register a new account</p>

      <?php if($errors): ?><div class="auth-err"><ul><?php foreach($errors as $e_): ?><li><?= e($e_) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="af"><i class="bi bi-person"></i><input type="text" name="full_name" value="<?= e($old['full_name']) ?>" placeholder="Username" required autocomplete="username"></div>
        <div class="af"><i class="bi bi-envelope"></i><input type="email" name="email" value="<?= e($old['email']) ?>" placeholder="Email Address" required autocomplete="email"></div>
        <div class="af"><i class="bi bi-lock"></i><input type="password" name="password" id="pw1" placeholder="Password" required minlength="8" oninput="chkPw(this.value)" autocomplete="new-password"><button type="button" class="eye" onclick="tg('pw1','e1')"><i class="bi bi-eye" id="e1"></i></button></div>
        <div class="pw-bar-wrap"><div class="pw-bar" id="pwBar"></div></div>
        <div class="af"><i class="bi bi-lock-fill"></i><input type="password" name="confirm_password" id="pw2" placeholder="Confirm Password" required minlength="8" autocomplete="new-password"><button type="button" class="eye" onclick="tg('pw2','e2')"><i class="bi bi-eye" id="e2"></i></button></div>
        <button type="submit" class="btn-auth-primary"><i class="bi bi-person-plus"></i>&nbsp;Create Account</button>
      </form>

      <div class="auth-or"><hr><span>OR</span><hr></div>
      <a href="<?= BASE_URL ?>admin/login.php" style="text-decoration:none;"><button class="btn-auth-ghost"><i class="bi bi-shield-lock-fill"></i> Register as Admin</button></a>
      <p class="auth-foot">Already have an account? <a href="<?= BASE_URL ?>user/login.php">Login Now</a></p>
    </div>
  </div>
</div>
<p class="auth-copy">&copy; <?= date('Y') ?> ParkVision. All rights reserved.</p>
<script>
function tg(f,i){const el=document.getElementById(f),ic=document.getElementById(i);el.type=el.type==='password'?'text':'password';ic.className=el.type==='password'?'bi bi-eye':'bi bi-eye-slash';}
function chkPw(v){let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const c=['#ef4444','#f59e0b','#22c55e','#2563eb'];const b=document.getElementById('pwBar');b.style.width=(s*25)+'%';b.style.background=c[s-1]||'';}
</script>
</body></html>
