<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_user_login();
$pdo = db();

$stmt = $pdo->prepare("SELECT r.reservation_id, r.booking_date, r.arrival_time, r.status, s.slot_code
     FROM reservations r JOIN parking_slots s ON s.slot_id = r.slot_id
     WHERE r.user_id = ? AND r.status IN ('pending','confirmed') ORDER BY r.created_at DESC LIMIT 1");
$stmt->execute([$user['user_id']]); $activeBooking = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM reservations WHERE user_id = ?");
$stmt->execute([$user['user_id']]); $totalBookings = (int)$stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT pr.record_id, pr.entry_time, pr.exit_time, pr.fee_amount, s.slot_code
     FROM parking_records pr JOIN vehicles v ON v.vehicle_id=pr.vehicle_id
     JOIN parking_slots s ON s.slot_id=pr.slot_id WHERE v.user_id=? ORDER BY pr.entry_time DESC LIMIT 5");
$stmt->execute([$user['user_id']]); $history = $stmt->fetchAll();

$pageTitle = 'Dashboard'; $activeUser = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Dashboard layout ── */
.dash-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:1.4rem; flex-wrap:wrap; gap:.75rem; }
.dash-header h4 { font-size:1.3rem; font-weight:800; color:var(--pv-text); margin:0; }
.livetime-badge { display:flex; align-items:center; gap:.45rem; background:rgba(37,99,235,.1); border:1px solid rgba(56,130,235,.22); border-radius:.55rem; padding:.35rem .8rem; font-size:.82rem; font-weight:600; color:#93c5fd; font-family:'Inter',monospace; }
.livetime-badge i { color:#38bdf8; font-size:.85rem; }

/* Stat cards */
.stat-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.4rem; }
@media(max-width:700px){ .stat-cards { grid-template-columns:1fr; } }

/* Main grid */
.dash-main { display:grid; grid-template-columns:1fr 340px; gap:1.3rem; }
@media(max-width:1050px){ .dash-main { grid-template-columns:1fr; } }

/* Slot section */
.slot-section { background:var(--pv-card); border:1px solid var(--pv-border-s); border-radius:1rem; overflow:hidden; }
.slot-section-head { padding:1rem 1.25rem; border-bottom:1px solid var(--pv-border-s); display:flex; align-items:center; justify-content:space-between; }
.slot-section-head h6 { font-weight:700; font-size:.95rem; color:var(--pv-text); margin:0; }
.slot-legend { display:flex; gap:.65rem; flex-wrap:wrap; }
.leg-item { display:flex; align-items:center; gap:.3rem; font-size:.72rem; color:var(--pv-text-muted); }
.leg-dot { width:10px; height:10px; border-radius:2px; }
.slot-body { padding:1.1rem 1.25rem; }

/* Book Slot inline panel */
.book-panel { background:var(--pv-card); border:1px solid var(--pv-border-s); border-radius:1rem; overflow:hidden; }
.book-panel-head { padding:1rem 1.25rem; border-bottom:1px solid var(--pv-border-s); display:flex; align-items:center; gap:.55rem; }
.book-panel-head h6 { font-weight:700; font-size:.95rem; color:var(--pv-text); margin:0; }
.book-panel-head i { color:#38bdf8; }
.book-panel-body { padding:1.1rem 1.25rem; }

.bk-field { margin-bottom:.85rem; }
.bk-field label { display:block; font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--pv-text-muted); margin-bottom:.38rem; }
.bk-field input, .bk-field select {
  width:100%; padding:.68rem .9rem; border:1.5px solid rgba(255,255,255,.12);
  border-radius:.65rem; background:rgba(255,255,255,.05); color:var(--pv-text);
  font-size:.9rem; outline:none; transition:border-color .2s, box-shadow .2s;
}
.bk-field input:focus, .bk-field select:focus { border-color:#0d9488; box-shadow:0 0 0 3px rgba(13,148,136,.18); }
.bk-field select option { background:var(--pv-card); color:var(--pv-text); }
.bk-field input::placeholder { color:var(--pv-text-muted); }

.btn-book-search { width:100%; padding:.72rem; border:none; border-radius:.65rem; margin-bottom:1rem;
  background:rgba(37,99,235,.15); border:1px solid rgba(56,130,235,.28); color:#93c5fd;
  font-weight:600; font-size:.9rem; cursor:pointer; transition:background .2s;
  display:flex; align-items:center; justify-content:center; gap:.45rem; }
.btn-book-search:hover { background:rgba(37,99,235,.25); }

/* Mini slot grid in book panel */
.mini-slots { display:grid; grid-template-columns:repeat(auto-fill,minmax(70px,1fr)); gap:.5rem; margin-bottom:.85rem; max-height:200px; overflow-y:auto; }
.mini-slots::-webkit-scrollbar { width:4px; }
.mini-slots::-webkit-scrollbar-thumb { background:rgba(56,130,235,.35); border-radius:3px; }
.mini-slot { border-radius:.55rem; padding:.55rem .3rem; text-align:center; font-weight:700; font-size:.78rem; color:#fff; cursor:pointer; border:none; transition:transform .15s, filter .15s; }
.mini-slot:hover { transform:translateY(-2px); filter:brightness(1.12); }
.mini-slot.status-available   { background:linear-gradient(150deg,#22c55e,#15803d); }
.mini-slot.status-reserved    { background:linear-gradient(150deg,#f59e0b,#b45309); cursor:not-allowed; }
.mini-slot.status-occupied    { background:linear-gradient(150deg,#ef4444,#b91c1c); cursor:not-allowed; }
.mini-slot.status-maintenance { background:linear-gradient(150deg,#475569,#334155); cursor:not-allowed; }

.book-confirm { background:rgba(37,99,235,.08); border:1px solid rgba(56,130,235,.22); border-radius:.75rem; padding:.85rem 1rem; margin-bottom:.85rem; display:none; }
.book-confirm .bc-slot { font-size:1.05rem; font-weight:800; color:#38bdf8; margin-bottom:.6rem; }
.btn-confirm-book { width:100%; padding:.75rem; border:none; border-radius:.65rem; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; font-weight:700; font-size:.95rem; cursor:pointer; box-shadow:0 4px 14px -3px rgba(37,99,235,.55); transition:filter .15s, transform .15s; display:flex; align-items:center; justify-content:center; gap:.5rem; }
.btn-confirm-book:hover { filter:brightness(1.1); transform:translateY(-1px); }

.book-msg { padding:.65rem .9rem; border-radius:.65rem; font-size:.84rem; font-weight:600; margin-bottom:.7rem; display:none; }
.book-msg.success { background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3); color:#86efac; }
.book-msg.danger  { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.3);  color:#fca5a5; }

/* History card */
.history-card { background:var(--pv-card); border:1px solid var(--pv-border-s); border-radius:1rem; overflow:hidden; margin-top:1.3rem; }
.history-head { padding:1rem 1.25rem; border-bottom:1px solid var(--pv-border-s); display:flex; align-items:center; gap:.55rem; }
.history-head h6 { font-weight:700; font-size:.95rem; color:var(--pv-text); margin:0; }

/* Active booking bar */
.active-bar { background:rgba(37,99,235,.1); border:1px solid rgba(56,130,235,.25); border-radius:.75rem; padding:.82rem 1.1rem; margin-bottom:1.2rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; font-size:.87rem; color:#93c5fd; }
.active-bar strong { color:#fff; }
.btn-view-booking { background:rgba(37,99,235,.2); border:1px solid rgba(56,130,235,.3); color:#93c5fd; border-radius:.55rem; padding:.35rem .8rem; font-size:.82rem; font-weight:600; text-decoration:none; transition:background .2s; }
.btn-view-booking:hover { background:rgba(37,99,235,.35); color:#fff; }
</style>

<!-- Header row with live time -->
<div class="dash-header">
  <h4>Welcome back, <?= e($user['full_name']) ?> 👋</h4>
  <div class="livetime-badge">
    <i class="bi bi-clock-fill"></i>
    <span id="userClockDisplay">--:--:--</span>
    &nbsp;·&nbsp;
    <span id="userDateDisplay"></span>
  </div>
</div>

<!-- Stat cards -->
<div class="stat-cards mb-4">
  <div class="stat-card bg-grad-blue"><span>Total Bookings</span><h3><?= $totalBookings ?></h3></div>
  <div class="stat-card bg-grad-green"><span>Active Booking</span><h3><?= $activeBooking ? e($activeBooking['slot_code']) : '—' ?></h3></div>
  <div class="stat-card bg-grad-purple"><span>Parking History</span><h3><?= count($history) ?></h3></div>
</div>

<!-- Active booking alert -->
<?php if ($activeBooking): ?>
<div class="active-bar">
  <span>Active reservation for slot <strong><?= e($activeBooking['slot_code']) ?></strong> on <?= e($activeBooking['booking_date']) ?> at <?= e(substr($activeBooking['arrival_time'],0,5)) ?></span>
  <a href="<?= BASE_URL ?>user/my_bookings.php" class="btn-view-booking">View / Cancel</a>
</div>
<?php endif; ?>

<!-- Main grid: slot grid left + book panel right -->
<div class="dash-main">

  <!-- Slot Grid -->
  <div class="slot-section">
    <div class="slot-section-head">
      <h6><i class="bi bi-grid-3x3-gap-fill me-2" style="color:#38bdf8"></i>Live Parking Slot Status</h6>
      <div class="slot-legend">
        <div class="leg-item"><div class="leg-dot" style="background:#22c55e"></div>Available</div>
        <div class="leg-item"><div class="leg-dot" style="background:#f59e0b"></div>Reserved</div>
        <div class="leg-item"><div class="leg-dot" style="background:#ef4444"></div>Occupied</div>
      </div>
    </div>
    <div class="slot-body"><div id="slotGrid" class="slot-grid"></div></div>
  </div>

  <!-- Book Slot inline panel -->
  <div>
    <div class="book-panel">
      <div class="book-panel-head">
        <i class="bi bi-calendar2-plus"></i>
        <h6>Book a Slot</h6>
      </div>
      <div class="book-panel-body">

        <div class="book-msg" id="bookMsg"></div>

        <div class="bk-field">
          <label>Date</label>
          <input type="date" id="bkDate" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="bk-field">
          <label>Arrival Time</label>
          <input type="time" id="bkTime">
        </div>
        <div class="bk-field">
          <label>Vehicle Plate</label>
          <input type="text" id="bkPlate" placeholder="e.g. WP-CAB-1234" style="text-transform:uppercase;">
        </div>
        <div class="bk-field">
          <label>Vehicle Type</label>
          <select id="bkType">
            <option value="car">Car</option>
            <option value="bike">Bike</option>
            <option value="suv">SUV</option>
            <option value="van">Van</option>
            <option value="ev">EV</option>
          </select>
        </div>

        <button class="btn-book-search" onclick="searchSlots()">
          <i class="bi bi-search"></i> Show Available Slots
        </button>

        <!-- Mini slot selector -->
        <div id="miniSlotWrap" style="display:none;">
          <div style="font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--pv-text-muted);margin-bottom:.5rem;">Tap a slot to select</div>
          <div class="mini-slots" id="miniSlotGrid"></div>
        </div>

        <!-- Confirm booking -->
        <div class="book-confirm" id="bookConfirm">
          <div class="bc-slot">Slot: <span id="bcSlotCode"></span></div>
          <div style="font-size:.8rem;color:var(--pv-text-muted);margin-bottom:.5rem;">Rate: <span id="bcRate"></span>/hr · <span id="bcDate"></span> at <span id="bcTime"></span></div>
          <button class="btn-confirm-book" onclick="confirmBook()">
            <i class="bi bi-check-circle-fill"></i> Confirm Booking
          </button>
        </div>
      </div>
    </div>

    <!-- History -->
    <div class="history-card">
      <div class="history-head"><i class="bi bi-clock-history" style="color:#38bdf8"></i><h6>Recent Parking History</h6></div>
      <ul class="list-group list-group-flush">
        <?php if (!$history): ?>
          <li class="list-group-item" style="color:var(--pv-text-muted);font-size:.88rem;">No parking history yet.</li>
        <?php endif; ?>
        <?php foreach ($history as $h): ?>
          <li class="list-group-item d-flex justify-content-between" style="font-size:.85rem;">
            <span><strong><?= e($h['slot_code']) ?></strong> · <?= e(date('M j', strtotime($h['entry_time']))) ?></span>
            <span style="color:<?= $h['fee_amount'] !== null ? '#4ade80' : '#f59e0b' ?>;font-weight:600;"><?= $h['fee_amount'] !== null ? format_money((float)$h['fee_amount']) : 'In progress' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<script>
const BASE_URL = "<?= BASE_URL ?>";
const CSRF = "<?= csrf_token() ?>";
let selectedSlot = null;

/* Live slot grid */
function refreshSlots() {
  fetch(`${BASE_URL}api/slots_status.php`).then(r=>r.json()).then(data=>{
    if (!data.success) return;
    const grid = document.getElementById('slotGrid');
    grid.innerHTML = '';
    data.slots.forEach(s => {
      const d = document.createElement('div');
      d.className = `slot-card status-${s.status}`;
      d.textContent = s.slot_code; d.title = s.status;
      grid.appendChild(d);
    });
  });
}
refreshSlots(); setInterval(refreshSlots, 5000);

/* Search available slots */
async function searchSlots() {
  const date = document.getElementById('bkDate').value;
  const time = document.getElementById('bkTime').value;
  if (!date || !time) { showBookMsg('Please select date and time.','danger'); return; }
  showBookMsg('','');

  const r = await fetch(`${BASE_URL}api/slots_status.php`);
  const data = await r.json();
  if (!data.success) { showBookMsg('Could not load slots.','danger'); return; }

  const wrap = document.getElementById('miniSlotWrap');
  const grid = document.getElementById('miniSlotGrid');
  grid.innerHTML = '';
  data.slots.forEach(s => {
    const d = document.createElement('button');
    d.className = `mini-slot status-${s.status}`;
    d.textContent = s.slot_code;
    d.disabled = s.status !== 'available';
    if (s.status === 'available') {
      d.onclick = () => selectSlot(s.slot_id, s.slot_code, s.hourly_rate);
    }
    grid.appendChild(d);
  });
  wrap.style.display = 'block';
  document.getElementById('bookConfirm').style.display = 'none';
  selectedSlot = null;
}

function selectSlot(id, code, rate) {
  selectedSlot = { id, code, rate };
  const confirm = document.getElementById('bookConfirm');
  document.getElementById('bcSlotCode').textContent = code;
  document.getElementById('bcRate').textContent = 'Rs. ' + parseFloat(rate).toFixed(2);
  document.getElementById('bcDate').textContent = document.getElementById('bkDate').value;
  document.getElementById('bcTime').textContent = document.getElementById('bkTime').value;
  confirm.style.display = 'block';
  confirm.scrollIntoView({behavior:'smooth', block:'nearest'});
  // highlight selected
  document.querySelectorAll('.mini-slot').forEach(b => b.style.outline = '');
  event.target.style.outline = '2.5px solid #38bdf8';
}

async function confirmBook() {
  if (!selectedSlot) return;
  const plate = document.getElementById('bkPlate').value.trim().toUpperCase();
  const type  = document.getElementById('bkType').value;
  const date  = document.getElementById('bkDate').value;
  const time  = document.getElementById('bkTime').value;
  if (!plate) { showBookMsg('Please enter your vehicle plate number.','danger'); return; }

  const btn = document.querySelector('.btn-confirm-book');
  btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Booking…';

  const fd = new FormData();
  fd.append('csrf_token', CSRF);
  fd.append('slot_id', selectedSlot.id);
  fd.append('plate_number', plate);
  fd.append('vehicle_type', type);
  fd.append('booking_date', date);
  fd.append('arrival_time', time);

  const resp = await fetch(`${BASE_URL}api/book_slot.php`, {method:'POST', body:fd});
  const data = await resp.json();

  btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirm Booking';

  if (data.success) {
    showBookMsg(`✓ Slot ${selectedSlot.code} booked! Reload to see your booking.`, 'success');
    document.getElementById('bookConfirm').style.display = 'none';
    document.getElementById('miniSlotWrap').style.display = 'none';
    selectedSlot = null;
    refreshSlots();
  } else {
    showBookMsg(data.message || 'Booking failed.', 'danger');
  }
}

function showBookMsg(msg, type) {
  const el = document.getElementById('bookMsg');
  if (!msg) { el.style.display='none'; return; }
  el.textContent = msg; el.className = `book-msg ${type}`; el.style.display='block';
  setTimeout(() => { el.style.display='none'; }, 6000);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
