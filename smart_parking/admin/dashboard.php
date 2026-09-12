<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin_login();

// 7-day revenue trend for the Chart.js line chart.
$pdo = db();
$stmt = $pdo->query(
    "SELECT DATE(paid_at) d, COALESCE(SUM(amount),0) total
     FROM payments
     WHERE payment_status = 'paid' AND paid_at >= (CURDATE() - INTERVAL 6 DAY)
     GROUP BY DATE(paid_at)"
);
$revenueByDate = [];
foreach ($stmt->fetchAll() as $row) {
    $revenueByDate[$row['d']] = (float) $row['total'];
}
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chartLabels[] = date('M j', strtotime($d));
    $chartData[] = $revenueByDate[$d] ?? 0;
}

// Slot-type breakdown for the doughnut chart.
$typeStmt = $pdo->query("SELECT slot_type, COUNT(*) c FROM parking_slots GROUP BY slot_type");
$typeLabels = [];
$typeData = [];
foreach ($typeStmt->fetchAll() as $row) {
    $typeLabels[] = ucfirst($row['slot_type']);
    $typeData[] = (int) $row['c'];
}

// Vehicles parked longer than OVERSTAY_ALERT_HOURS — possible abandoned cars
// or drivers who forgot to scan out at exit.
$overstayVehicles = get_overstay_vehicles($pdo, OVERSTAY_ALERT_HOURS);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3" id="statCards">
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-blue"><span>Total Slots</span><h3 id="statTotal">-</h3></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-green"><span>Available</span><h3 id="statAvailable">-</h3></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-orange"><span>Reserved</span><h3 id="statReserved">-</h3></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-red"><span>Occupied</span><h3 id="statOccupied">-</h3></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-purple"><span>Vehicles Inside</span><h3 id="statInside">-</h3></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-blue"><span>Today's Bookings</span><h3 id="statBookings">-</h3></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-green"><span>Today's Entries / Exits</span><h3 id="statEntriesExits">-</h3></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card bg-grad-orange"><span>Today's Revenue</span><h3 id="statRevenue">-</h3></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header pv-card-header">Revenue — Last 7 Days</div>
      <div class="card-body"><canvas id="revenueChart" height="140"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header pv-card-header">Slots by Type</div>
      <div class="card-body"><canvas id="typeChart" height="140"></canvas></div>
    </div>
  </div>
</div>

<?php if (!empty($overstayVehicles)): ?>
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0" style="border-left:4px solid #ef4444 !important;">
      <div class="card-header pv-card-header d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill" style="color:#ef4444;"></i>
        Overstay Alert — <?= count($overstayVehicles) ?> vehicle<?= count($overstayVehicles) === 1 ? '' : 's' ?> parked over <?= (int) OVERSTAY_ALERT_HOURS ?>h
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead><tr><th class="ps-3">Plate</th><th>Type</th><th>Slot</th><th>Entered</th><th>Duration</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($overstayVehicles as $ov):
                  $h = intdiv((int)$ov['minutes_parked'], 60);
                  $m = (int)$ov['minutes_parked'] % 60;
              ?>
              <tr>
                <td class="ps-3 fw-bold"><?= e($ov['plate_number']) ?></td>
                <td class="text-capitalize"><?= e($ov['vehicle_type']) ?></td>
                <td><?= e($ov['slot_code']) . e($ov['sub_slot'] ?? '') ?></td>
                <td><?= e($ov['entry_time']) ?></td>
                <td><span class="badge" style="background:rgba(239,68,68,.15);color:#fca5a5;"><?= $h ?>h <?= $m ?>min</span></td>
                <td><a href="scan_exit.php" class="small">Process exit &rarr;</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm border-0">
      <div class="card-header pv-card-header d-flex justify-content-between">
        Live Slot Status
        <a href="manage_slots.php" class="small">Manage slots &rarr;</a>
      </div>
      <div class="card-body">
        <div class="slot-grid" id="slotGrid"><div class="text-muted">Loading...</div></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card shadow-sm border-0">
      <div class="card-header pv-card-header">Live Activity Feed</div>
      <div class="card-body" id="liveActivity"><div class="text-muted">Loading...</div></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>

// Global Chart.js defaults — white/light text
Chart.defaults.color = '#a0b8d8';
Chart.defaults.borderColor = 'rgba(56,130,235,.15)';

const revenueChart = new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Revenue (<?= e(APP_CURRENCY_SYMBOL) ?>)',
      data: <?= json_encode($chartData) ?>,
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59,130,246,0.15)',
      tension: 0.35,
      fill: true,
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: 'rgba(56,130,235,.15)' }, ticks: { color: '#8cb0e0', font: { size: 11 } } },
      x: { grid: { color: 'rgba(56,130,235,.1)' }, ticks: { color: '#8cb0e0', font: { size: 11 } } }
    }
  }
});

const typeChart = new Chart(document.getElementById('typeChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($typeLabels) ?>,
    datasets: [{
      data: <?= json_encode($typeData) ?>,
      backgroundColor: ['#3b82f6','#22c55e','#fb923c','#f87171','#a78bfa','#fbbf24']
    }]
  },
  options: { plugins: { legend: { position: 'bottom', labels: { color: '#c8d8f0', padding: 14, font: { size: 12 } } } } }
});

async function refreshDashboard() {
  try {
    const res = await fetch('<?= BASE_URL ?>api/slots_status.php');
    const data = await res.json();
    if (!data.success) return;

    document.getElementById('statTotal').textContent = data.total_slots;
    document.getElementById('statAvailable').textContent = data.counts.available;
    document.getElementById('statReserved').textContent = data.counts.reserved;
    document.getElementById('statOccupied').textContent = data.counts.occupied;
    document.getElementById('statInside').textContent = data.vehicles_inside;
    document.getElementById('statBookings').textContent = data.todays_bookings;
    document.getElementById('statEntriesExits').textContent = data.todays_entries + ' / ' + data.todays_exits;
    document.getElementById('statRevenue').textContent = '<?= e(APP_CURRENCY_SYMBOL) ?> ' + Number(data.total_revenue).toFixed(2);

    const grid = document.getElementById('slotGrid');
    grid.innerHTML = data.slots.map(s =>
      `<div class="slot-card status-${s.status}" title="${s.zone} · ${s.slot_type}">${s.slot_code}</div>`
    ).join('');

    const feed = document.getElementById('liveActivity');
    feed.innerHTML = data.activity.map(a =>
      `<div class="border-bottom py-2 small">
         <span class="badge bg-secondary">${a.actor_type}</span>
         <strong>${a.action}</strong><br>
         <span class="text-muted">${a.description ?? ''}</span>
         <div class="text-muted" style="font-size:.75rem;">${a.created_at}</div>
       </div>`
    ).join('') || '<div class="text-muted">No recent activity.</div>';
  } catch (e) { console.error(e); }
}
refreshDashboard();
setInterval(refreshDashboard, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
