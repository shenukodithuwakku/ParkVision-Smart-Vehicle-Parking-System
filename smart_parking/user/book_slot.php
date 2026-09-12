<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_user_login();
$pageTitle  = 'Book a Parking Slot';
$activeUser = 'book';
include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-1"><i class="bi bi-calendar2-check me-2"></i>Book a Parking Slot</h4>
<p class="text-muted small mb-4">Select your vehicle type first — only matching slots will be shown.</p>

<div class="card mb-4">
  <div class="card-body">
    <form id="filterForm" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Vehicle Type</label>
        <select id="vehicleTypeFilter" class="form-select">
          <option value="car">🚗 Car / SUV / EV</option>
          <option value="bike">🏍️ Bike / Motorbike</option>
          <option value="van">🚐 Van / Truck</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" id="bookingDate" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Arrival Time</label>
        <input type="time" id="arrivalTime" class="form-control" required>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Show Available Slots</button>
      </div>
    </form>
  </div>
</div>

<div id="slotGridWrapper" class="card d-none mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h6 class="fw-bold mb-0">Tap a slot to reserve it</h6>
        <small class="text-muted" id="slotTypeHint"></small>
      </div>
      <div class="small">
        <span class="badge" style="background:var(--pv-success)">&nbsp;</span> Available
        <span class="badge ms-2" style="background:var(--pv-warning)">&nbsp;</span> Reserved
        <span class="badge ms-2" style="background:var(--pv-danger)">&nbsp;</span> Full
        <span class="badge ms-2" style="background:var(--pv-secondary)">&nbsp;</span> Maintenance
      </div>
    </div>
    <div id="slotGrid" class="slot-grid"></div>
  </div>
</div>

<!-- Booking modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Booking — <span id="modalSlotLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="bookError" class="alert alert-danger d-none"></div>
        <div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-3" id="slotInfoAlert">
          <i class="bi bi-info-circle-fill mt-1"></i>
          <span id="slotInfoText"></span>
        </div>
        <div class="mb-3">
          <label class="form-label">Vehicle Number Plate</label>
          <input type="text" id="plateNumber" class="form-control text-uppercase" placeholder="e.g. WP-CAB-1234" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Vehicle Type</label>
          <select id="vehicleType" class="form-select" disabled>
            <option value="car">Car</option>
            <option value="bike">Bike</option>
            <option value="van">Van</option>
          </select>
          <div class="form-text">Type is auto-selected based on slot.</div>
        </div>
        <p class="small text-muted mb-0">Rate: <strong id="modalRate"></strong>/hr. Slot released if you don't arrive within grace period.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmBookBtn"><i class="bi bi-qr-code me-1"></i>Confirm &amp; Get QR</button>
      </div>
    </div>
  </div>
</div>

<!-- Success modal -->
<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content text-center">
      <div class="modal-body p-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
        <h5 class="mt-3">Booking Confirmed!</h5>
        <p class="text-muted">Show this QR code at the entrance gate.</p>
        <div class="mb-2 fw-bold" id="qrSlotDisplay"></div>
        <div class="d-flex justify-content-center my-3"><div id="qrCanvasHolder"></div></div>
        <a href="<?= BASE_URL ?>user/my_bookings.php" class="btn btn-primary">Go to My Bookings</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const BASE_URL   = "<?= BASE_URL ?>";
const CSRF_TOKEN = "<?= csrf_token() ?>";
let selectedSlot = null;

document.getElementById('bookingDate').valueAsDate = new Date();

document.getElementById('filterForm').addEventListener('submit', function(e) {
  e.preventDefault();
  loadSlots();
});

function loadSlots() {
  const date  = document.getElementById('bookingDate').value;
  const time  = document.getElementById('arrivalTime').value;
  const vtype = document.getElementById('vehicleTypeFilter').value;
  if (!date || !time) return;

  const grid    = document.getElementById('slotGrid');
  const wrapper = document.getElementById('slotGridWrapper');
  grid.innerHTML = '<div class="text-muted p-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading slots…</div>';
  wrapper.classList.remove('d-none');

  fetch(`${BASE_URL}api/available_slots.php?date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}&vehicle_type=${encodeURIComponent(vtype)}`)
    .then(r => r.json())
    .then(data => {
      grid.innerHTML = '';
      if (!data.success) { grid.innerHTML = `<div class="alert alert-warning">${data.message}</div>`; return; }
      if (!data.slots.length) { grid.innerHTML = `<div class="alert alert-warning">No ${vtype} slots found.</div>`; return; }

      const typeLabels = {car:'Car / SUV / EV', bike:'Bike / Motorbike', van:'Van / Truck'};
      document.getElementById('slotTypeHint').textContent =
        `Showing ${data.slots.length} ${typeLabels[vtype]||vtype} slot(s)`;

      data.slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        // Colour: available (green), partial bikes (amber), full/reserved (red/orange), maintenance (gray)
        let cls = 'status-occupied';
        if (slot.status === 'maintenance') cls = 'status-maintenance';
        else if (slot.available) cls = slot.is_shared ? (slot.remaining < slot.capacity ? 'status-reserved' : 'status-available') : 'status-available';
        else if (slot.status === 'reserved') cls = 'status-reserved';

        btn.className = `slot-card ${cls}`;
        btn.disabled  = !slot.available;

        // Label: "A-B1\n8/10" for bikes
        if (slot.is_shared) {
          btn.innerHTML = `<div>${slot.slot_code}</div><div style="font-size:.7rem;opacity:.85">${slot.remaining}/${slot.capacity}</div>`;
        } else {
          btn.textContent = slot.slot_code;
        }
        btn.title = slot.available
          ? `${slot.zone} · ${slot.slot_type} · Rs.${slot.hourly_rate}/hr${slot.is_shared ? ` · ${slot.remaining} space(s) left` : ''}`
          : `${slot.slot_type} · ${slot.status}`;

        if (slot.available) btn.addEventListener('click', () => openBookModal(slot, date, time, vtype));
        grid.appendChild(btn);
      });
    })
    .catch(() => { grid.innerHTML = '<div class="alert alert-danger">Failed to load slots. Please try again.</div>'; });
}

function openBookModal(slot, date, time, vtype) {
  selectedSlot = { ...slot, date, time, vtype };

  // Auto-set vehicle type in modal from slot type
  const vtSelect = document.getElementById('vehicleType');
  vtSelect.value = slot.slot_type === 'bike' ? 'bike' : (slot.slot_type === 'van' ? 'van' : 'car');

  document.getElementById('modalSlotLabel').textContent = slot.slot_code;
  document.getElementById('modalRate').textContent = 'Rs. ' + slot.hourly_rate.toFixed(2);
  document.getElementById('plateNumber').value = '';
  document.getElementById('bookError').classList.add('d-none');

  const info = document.getElementById('slotInfoText');
  if (slot.is_shared) {
    info.textContent = `Bike slot ${slot.slot_code} — ${slot.remaining} of ${slot.capacity} spaces available. You will be assigned sub-slot A/B/C… at entry.`;
    document.getElementById('slotInfoAlert').classList.remove('d-none');
  } else {
    document.getElementById('slotInfoAlert').classList.add('d-none');
  }

  new bootstrap.Modal(document.getElementById('bookModal')).show();
}

document.getElementById('confirmBookBtn').addEventListener('click', function() {
  const plate  = document.getElementById('plateNumber').value.trim();
  const vtype  = document.getElementById('vehicleType').value;
  const errBox = document.getElementById('bookError');
  errBox.classList.add('d-none');

  if (!plate) { errBox.textContent = 'Please enter your vehicle number plate.'; errBox.classList.remove('d-none'); return; }

  this.disabled = true;
  this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';
  const btn = this;

  const fd = new FormData();
  fd.append('csrf_token', CSRF_TOKEN);
  fd.append('slot_id', selectedSlot.slot_id);
  fd.append('booking_date', selectedSlot.date);
  fd.append('arrival_time', selectedSlot.time);
  fd.append('plate_number', plate);
  fd.append('vehicle_type', vtype);

  fetch(`${BASE_URL}api/book_slot_process.php`, { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-qr-code me-1"></i>Confirm & Get QR';
      if (!data.success) {
        errBox.textContent = data.message;
        errBox.classList.remove('d-none');
        if (data.message.toLowerCase().includes('taken') || data.message.toLowerCase().includes('just')) loadSlots();
        return;
      }
      bootstrap.Modal.getInstance(document.getElementById('bookModal')).hide();
      document.getElementById('qrCanvasHolder').innerHTML = '';
      document.getElementById('qrSlotDisplay').textContent = 'Slot: ' + selectedSlot.slot_code;
      new QRCode(document.getElementById('qrCanvasHolder'), { text: data.qr_token, width: 200, height: 200 });
      new bootstrap.Modal(document.getElementById('successModal')).show();
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-qr-code me-1"></i>Confirm & Get QR';
      errBox.textContent = 'Network error. Please try again.';
      errBox.classList.remove('d-none');
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
