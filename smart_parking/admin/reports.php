<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin_login();
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 day'));
$to   = $_GET['to'] ?? date('Y-m-d');

// Revenue + counts summary for the range.
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(p.amount),0) revenue, COUNT(*) txn_count
     FROM payments p WHERE p.payment_status = 'paid' AND DATE(p.paid_at) BETWEEN ? AND ?"
);
$stmt->execute([$from, $to]);
$summary = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM reservations WHERE booking_date BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$totalBookings = (int) $stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM parking_records WHERE DATE(entry_time) BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$totalEntries = (int) $stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM parking_records WHERE exit_time IS NOT NULL AND DATE(exit_time) BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$totalExits = (int) $stmt->fetch()['c'];

// Daily revenue series for the chart.
$stmt = $pdo->prepare(
    "SELECT DATE(paid_at) d, SUM(amount) total FROM payments
     WHERE payment_status = 'paid' AND DATE(paid_at) BETWEEN ? AND ?
     GROUP BY DATE(paid_at) ORDER BY d"
);
$stmt->execute([$from, $to]);
$byDate = [];
foreach ($stmt->fetchAll() as $row) { $byDate[$row['d']] = (float) $row['total']; }
$labels = []; $series = [];
$cursor = strtotime($from);
$end = strtotime($to);
while ($cursor <= $end) {
    $d = date('Y-m-d', $cursor);
    $labels[] = date('M j', $cursor);
    $series[] = $byDate[$d] ?? 0;
    $cursor = strtotime('+1 day', $cursor);
}

// Detailed record list for the range.
$stmt = $pdo->prepare(
    "SELECT pr.entry_time, pr.exit_time, pr.duration_minutes, pr.fee_amount, pr.status,
            v.plate_number, s.slot_code, p.payment_method
     FROM parking_records pr
     JOIN vehicles v ON v.vehicle_id = pr.vehicle_id
     JOIN parking_slots s ON s.slot_id = pr.slot_id
     LEFT JOIN payments p ON p.record_id = pr.record_id
     WHERE DATE(pr.entry_time) BETWEEN ? AND ?
     ORDER BY pr.entry_time DESC"
);
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

$pageTitle = 'Reports';
$activeNav = 'reports';
include __DIR__ . '/includes/header.php';
?>

<style>
@media print {
  .admin-sidebar, .admin-topbar, .no-print { display: none !important; }
  .admin-content { width: 100% !important; }
}
</style>

<div class="card shadow-sm border-0 mb-3 no-print">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-auto"><label class="form-label small mb-1">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
      <div class="col-auto"><label class="form-label small mb-1">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit">Generate</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
        <a class="btn btn-sm btn-outline-success" href="export_csv.php?from=<?= e($from) ?>&to=<?= e($to) ?>"><i class="bi bi-filetype-csv"></i> Export to Excel (CSV)</a>
      </div>
    </form>
  </div>
</div>

<h5 class="mb-3">Report: <?= e($from) ?> &rarr; <?= e($to) ?></h5>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-green"><span>Revenue</span><h3><?= format_money((float) $summary['revenue']) ?></h3></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-blue"><span>Bookings</span><h3><?= $totalBookings ?></h3></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-orange"><span>Entries</span><h3><?= $totalEntries ?></h3></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card bg-grad-purple"><span>Exits</span><h3><?= $totalExits ?></h3></div></div>
</div>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-header pv-card-header">Revenue Trend</div>
  <div class="card-body"><canvas id="reportChart" height="100"></canvas></div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-header pv-card-header">Detailed Records (<?= count($rows) ?>)</div>
  <div class="card-body table-responsive">
    <table class="table table-compact table-hover align-middle">
      <thead><tr><th>Plate</th><th>Slot</th><th>Entry</th><th>Exit</th><th>Duration</th><th>Fee</th><th>Method</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-bold"><?= e($r['plate_number']) ?></td>
          <td><?= e($r['slot_code']) ?></td>
          <td class="small"><?= e($r['entry_time']) ?></td>
          <td class="small"><?= e($r['exit_time'] ?? '—') ?></td>
          <td><?= $r['duration_minutes'] ? (int) $r['duration_minutes'] . ' min' : '—' ?></td>
          <td><?= $r['fee_amount'] !== null ? format_money((float) $r['fee_amount']) : '—' ?></td>
          <td><?= e($r['payment_method'] ?? '—') ?></td>
          <td><span class="badge bg-<?= $r['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No records in this range.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('reportChart'), {
  type: 'bar',
  data: { labels: <?= json_encode($labels) ?>, datasets: [{ label: 'Revenue', data: <?= json_encode($series) ?>, backgroundColor: '#3b82f6' }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
