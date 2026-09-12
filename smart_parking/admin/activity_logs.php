<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin_login();
$pdo = db();

$actorFilter = $_GET['actor'] ?? '';
$sql = "SELECT * FROM activity_logs WHERE 1=1";
$params = [];
if (in_array($actorFilter, ['admin', 'user', 'system'], true)) {
    $sql .= ' AND actor_type = ?';
    $params[] = $actorFilter;
}
$sql .= ' ORDER BY log_id DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pageTitle = 'Activity Logs';
$activeNav = 'logs';
include __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body d-flex gap-2">
    <a href="?actor=" class="btn btn-sm btn-outline-secondary <?= $actorFilter === '' ? 'active' : '' ?>">All</a>
    <a href="?actor=admin" class="btn btn-sm btn-outline-secondary <?= $actorFilter === 'admin' ? 'active' : '' ?>">Admin</a>
    <a href="?actor=user" class="btn btn-sm btn-outline-secondary <?= $actorFilter === 'user' ? 'active' : '' ?>">User</a>
    <a href="?actor=system" class="btn btn-sm btn-outline-secondary <?= $actorFilter === 'system' ? 'active' : '' ?>">System</a>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body table-responsive">
    <table class="table table-compact table-hover align-middle">
      <thead><tr><th>#</th><th>Actor</th><th>Action</th><th>Description</th><th>IP</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td>#<?= (int) $l['log_id'] ?></td>
          <td><span class="badge bg-secondary"><?= e($l['actor_type']) ?></span></td>
          <td><?= e($l['action']) ?></td>
          <td class="small text-muted"><?= e($l['description'] ?? '') ?></td>
          <td class="small text-muted"><?= e($l['ip_address'] ?? '') ?></td>
          <td class="small"><?= e($l['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No activity recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
