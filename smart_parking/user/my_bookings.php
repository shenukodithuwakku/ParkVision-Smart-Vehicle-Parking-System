<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = require_user_login();

$stmt = db()->prepare(
    "SELECT r.reservation_id, r.booking_date, r.arrival_time, r.status, r.expires_at, r.created_at,
            s.slot_code, s.zone, s.hourly_rate,
            v.plate_number,
            q.code_value AS qr_token
     FROM reservations r
     JOIN parking_slots s ON s.slot_id = r.slot_id
     LEFT JOIN vehicles v ON v.vehicle_id = r.vehicle_id
     LEFT JOIN qr_codes q ON q.qr_id = r.qr_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC"
);
$stmt->execute([$user['user_id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'My Bookings';
include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-3"><i class="bi bi-journal-bookmark"></i> My Bookings</h4>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Slot</th><th>Vehicle</th><th>Date</th><th>Arrival</th><th>Status</th><th>QR</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$bookings): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">You have no bookings yet. <a href="<?= BASE_URL ?>user/book_slot.php">Book one now</a>.</td></tr>
          <?php endif; ?>
          <?php foreach ($bookings as $b): ?>
            <?php
              $badge = [
                  'confirmed' => 'primary', 'pending' => 'warning', 'cancelled' => 'secondary',
                  'expired' => 'danger', 'completed' => 'success',
              ][$b['status']] ?? 'secondary';
            ?>
            <tr>
              <td><strong><?= e($b['slot_code']) ?></strong><br><span class="text-muted small"><?= e($b['zone']) ?></span></td>
              <td><?= e($b['plate_number'] ?? '—') ?></td>
              <td><?= e($b['booking_date']) ?></td>
              <td><?= e(substr($b['arrival_time'], 0, 5)) ?></td>
              <td><span class="badge bg-<?= $badge ?>"><?= e(ucfirst($b['status'])) ?></span></td>
              <td>
                <?php if ($b['qr_token'] && in_array($b['status'], ['confirmed', 'pending'], true)): ?>
                  <button class="btn btn-sm btn-outline-secondary view-qr-btn" data-token="<?= e($b['qr_token']) ?>" data-slot="<?= e($b['slot_code']) ?>">
                    <i class="bi bi-qr-code"></i> View
                  </button>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if (in_array($b['status'], ['confirmed', 'pending'], true)): ?>
                  <button class="btn btn-sm btn-outline-danger cancel-btn" data-id="<?= (int) $b['reservation_id'] ?>">Cancel</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- QR view modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content text-center">
      <div class="modal-body p-4">
        <h5>QR Code &mdash; Slot <span id="qrModalSlot"></span></h5>
        <div class="qr-box d-flex justify-content-center my-3"><div id="qrModalCanvas"></div></div>
        <button class="btn btn-outline-primary" id="downloadQrBtn">Download</button>

        <hr class="my-3">
        <label class="form-label small text-muted mb-1">Camera scan wenne nathnam, exit eke manual token field eke methana copy karala paste karanna:</label>
        <div class="input-group">
          <input type="text" class="form-control text-center" id="qrModalToken" readonly style="font-family:monospace; font-size:.85rem;">
          <button class="btn btn-outline-secondary" type="button" id="copyTokenBtn"><i class="bi bi-clipboard"></i> Copy</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const BASE_URL = "<?= BASE_URL ?>";
const CSRF_TOKEN = "<?= csrf_token() ?>";

document.querySelectorAll('.view-qr-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('qrModalSlot').textContent = btn.dataset.slot;
    const holder = document.getElementById('qrModalCanvas');
    holder.innerHTML = '';
    new QRCode(holder, { text: btn.dataset.token, width: 220, height: 220 });
    document.getElementById('qrModalToken').value = btn.dataset.token;
    new bootstrap.Modal(document.getElementById('qrModal')).show();
  });
});

document.getElementById('copyTokenBtn').addEventListener('click', () => {
  const input = document.getElementById('qrModalToken');
  input.select();
  navigator.clipboard.writeText(input.value).then(() => {
    const btn = document.getElementById('copyTokenBtn');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
    setTimeout(() => { btn.innerHTML = original; }, 2000);
  }).catch(() => {
    document.execCommand('copy');
  });
});

document.getElementById('downloadQrBtn').addEventListener('click', () => {
  const img = document.querySelector('#qrModalCanvas img, #qrModalCanvas canvas');
  if (!img) return;
  const link = document.createElement('a');
  link.download = 'parking-qr-code.png';
  link.href = img.tagName === 'CANVAS' ? img.toDataURL('image/png') : img.src;
  link.click();
});

document.querySelectorAll('.cancel-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!confirm('Cancel this booking? The slot will become available to other users immediately.')) return;
    const fd = new FormData();
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('reservation_id', btn.dataset.id);
    fetch(`${BASE_URL}api/cancel_booking.php`, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        alert(data.message);
        if (data.success) location.reload();
      });
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
