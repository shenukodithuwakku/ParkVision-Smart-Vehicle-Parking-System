<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_user_login();
$pdo = db();

$stmt = $pdo->prepare('SELECT full_name, email, phone FROM users WHERE user_id = ?');
$stmt->execute([$user['user_id']]);
$profile = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = clean_input($_POST['full_name'] ?? '');
        $phone    = clean_input($_POST['phone'] ?? '');

        if (strlen($fullName) < 3) $errors[] = 'Full name is too short.';
        if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) $errors[] = 'Please enter a valid phone number.';

        if (!$errors) {
            $pdo->prepare('UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?')
                ->execute([$fullName, $phone, $user['user_id']]);
            $_SESSION['user_name'] = $fullName;
            set_flash('success', 'Profile updated successfully.');
            redirect(BASE_URL . 'user/profile.php');
        }
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$user['user_id']]);
        $row = $stmt->fetch();

        if (!password_verify($current, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (!$errors) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), $user['user_id']]);
            set_flash('success', 'Password changed successfully.');
            redirect(BASE_URL . 'user/profile.php');
        }
    }
}

$pageTitle = 'Profile';
include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-3"><i class="bi bi-person-circle"></i> Profile Management</h4>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Account Details</h6>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <div class="mb-3">
            <label class="form-label">Email (cannot be changed)</label>
            <input type="email" class="form-control" value="<?= e($profile['email']) ?>" disabled>
          </div>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= e($profile['full_name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($profile['phone']) ?>" required>
          </div>
          <button class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Change Password</h6>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button class="btn btn-primary">Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
