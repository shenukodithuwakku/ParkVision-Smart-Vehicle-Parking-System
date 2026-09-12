<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/qr.php';

$admin = require_admin_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $qrId = (int) ($_POST['qr_id'] ?? 0);

    if ($action === 'void') {
        mark_qr_used($qrId);
        log_activity('admin', $admin['admin_id'], 'qr_void', "Voided QR #$qrId");
        set_flash('success', 'QR code voided.');
    }
    redirect(BASE_URL . 'admin/qr_codes.php');
}

$filter = $_GET['filter'] ?? '';
$sql = "SELECT q.*,
        (SELECT v.plate_number FROM reservations r JOIN vehicles v ON v.vehicle_id = r.vehicle_id WHERE r.qr_id = q.qr_id LIMIT 1) AS res_plate,
        (SELECT v.plate_number FROM parking_records pr JOIN vehicles v ON v.vehicle_id = pr.vehicle_id WHERE pr.qr_id = q.qr_id LIMIT 1) AS rec_plate
        FROM qr_codes q WHERE 1=1";
if ($filter === 'unused') $sql .= ' AND q.is_used = 0';
if ($filter === 'used') $sql .= ' AND q.is_used = 1';
$sql .= ' ORDER BY q.created_at DESC LIMIT 300';
$qrCodes = $pdo->query($sql)->fetchAll();

$pageTitle = 'QR Codes';
$activeNav = 'qr_codes';
include __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body d-flex gap-2">
    <a href="?filter=" class="btn btn-sm btn-outline-secondary <?= $filter === '' ? 'active' : '' ?>">All</a>
    <a href="?filter=unused" class="btn btn-sm btn-outline-secondary <?= $filter === 'unused' ? 'active' : '' ?>">Unused / Active</a>
    <a href="?filter=used" class="btn btn-sm btn-outline-secondary <?= $filter === 'used' ? 'active' : '' ?>">Used</a>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body table-responsive">
    <table class="table table-compact table-hover align-middle">
      <thead><tr><th>#</th><th>Token</th><th>Linked Plate</th><th>Status</th><th>Created</th><th>Used At</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($qrCodes as $q): ?>
        <tr>
          <td>#<?= (int) $q['qr_id'] ?></td>
          <td><code class="small"><?= e(substr($q['code_value'], 0, 16)) ?>&hellip;</code></td>
          <td><?= e($q['res_plate'] ?? $q['rec_plate'] ?? '—') ?></td>
          <td><span class="badge bg-<?= $q['is_used'] ? 'secondary' : 'success' ?>"><?= $q['is_used'] ? 'Used' : 'Active' ?></span></td>
          <td class="small"><?= e($q['created_at']) ?></td>
          <td class="small"><?= e($q['used_at'] ?? '—') ?></td>
          <td>
            <a href="<?= BASE_URL ?>admin/qr_view.php?token=<?= urlencode($q['code_value']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View / Download QR"><i class="bi bi-qr-code"></i></a>
            <?php if (!$q['is_used']): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Void this QR code? It will no longer work for entry/exit.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="void">
                <input type="hidden" name="qr_id" value="<?= (int) $q['qr_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Void</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$qrCodes): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No QR codes found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
