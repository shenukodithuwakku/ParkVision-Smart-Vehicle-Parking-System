<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin_login();
$pdo = db();

$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo   = $_GET['to'] ?? '';

$sql = "SELECT pr.*, v.plate_number, s.slot_code, p.amount AS paid_amount, p.payment_method
        FROM parking_records pr
        JOIN vehicles v ON v.vehicle_id = pr.vehicle_id
        JOIN parking_slots s ON s.slot_id = pr.slot_id
        LEFT JOIN payments p ON p.record_id = pr.record_id
        WHERE 1=1";
$params = [];
if (in_array($statusFilter, ['in_progress', 'completed'], true)) {
    $sql .= ' AND pr.status = ?';
    $params[] = $statusFilter;
}
if ($dateFrom !== '') { $sql .= ' AND DATE(pr.entry_time) >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '')   { $sql .= ' AND DATE(pr.entry_time) <= ?'; $params[] = $dateTo; }
$sql .= ' ORDER BY pr.entry_time DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$pageTitle = 'Parking Records';
$activeNav = 'records';
include __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-auto">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
        </select>
      </div>
      <div class="col-auto"><label class="form-label small mb-1">From</label><input type="date" name="from" value="<?= e($dateFrom) ?>" class="form-control form-control-sm"></div>
      <div class="col-auto"><label class="form-label small mb-1">To</label><input type="date" name="to" value="<?= e($dateTo) ?>" class="form-control form-control-sm"></div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit">Filter</button>
        <a href="manage_records.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        <a href="reports.php" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-arrow-down"></i> Export Report</a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body table-responsive">
    <table class="table table-compact table-hover align-middle">
      <thead><tr><th>#</th><th>Plate</th><th>Slot</th><th>Entry</th><th>Exit</th><th>Duration</th><th>Fee</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($records as $r): ?>
        <tr>
          <td>#<?= (int) $r['record_id'] ?></td>
          <td class="fw-bold"><?= e($r['plate_number']) ?></td>
          <td><?= e($r['slot_code']) ?></td>
          <td class="small"><?= e($r['entry_time']) ?></td>
          <td class="small"><?= e($r['exit_time'] ?? '—') ?></td>
          <td><?= $r['duration_minutes'] ? (int) $r['duration_minutes'] . ' min' : '—' ?></td>
          <td><?= $r['fee_amount'] !== null ? format_money((float) $r['fee_amount']) : '—' ?></td>
          <td><span class="badge bg-<?= $r['status'] === 'completed' ? 'success' : 'warning' ?>"><?= $r['status'] === 'completed' ? 'Completed' : 'In Progress' ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$records): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No parking records match this filter.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
