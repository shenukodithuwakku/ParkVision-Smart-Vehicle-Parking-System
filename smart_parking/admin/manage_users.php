<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'block') {
        $pdo->prepare("UPDATE users SET status = 'blocked' WHERE user_id = ?")->execute([$userId]);
        log_activity('admin', $admin['admin_id'], 'user_block', "Blocked user #$userId");
        set_flash('success', 'User blocked.');
    } elseif ($action === 'unblock') {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = ?")->execute([$userId]);
        log_activity('admin', $admin['admin_id'], 'user_unblock', "Unblocked user #$userId");
        set_flash('success', 'User unblocked.');
    } elseif ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);
            log_activity('admin', $admin['admin_id'], 'user_delete', "Deleted user #$userId");
            set_flash('success', 'User deleted.');
        } catch (PDOException $e) {
            set_flash('error', 'Cannot delete this user — they have linked reservations. Block them instead.');
        }
    }
    redirect(BASE_URL . 'admin/manage_users.php');
}

$search = clean_input($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare(
        "SELECT u.*, (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.user_id) AS booking_count
         FROM users u WHERE u.full_name LIKE ? OR u.email LIKE ? ORDER BY u.created_at DESC"
    );
    $like = "%$search%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query(
        "SELECT u.*, (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.user_id) AS booking_count
         FROM users u ORDER BY u.created_at DESC"
    );
}
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
$activeNav = 'users';
include __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form class="d-flex gap-2" method="get">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name or email" value="<?= e($search) ?>">
      <button class="btn btn-sm btn-primary" type="submit">Search</button>
      <?php if ($search !== ''): ?><a href="manage_users.php" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body table-responsive">
    <table class="table table-compact table-hover align-middle">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Status</th><th>Joined</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone']) ?></td>
          <td><?= (int) $u['booking_count'] ?></td>
          <td><span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst($u['status']) ?></span></td>
          <td class="small text-muted"><?= e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
          <td class="text-end">
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
              <?php if ($u['status'] === 'active'): ?>
                <input type="hidden" name="action" value="block">
                <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Block this user?')">Block</button>
              <?php else: ?>
                <input type="hidden" name="action" value="unblock">
                <button class="btn btn-sm btn-outline-success" onclick="return confirm('Unblock this user?')">Unblock</button>
              <?php endif; ?>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this user? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
