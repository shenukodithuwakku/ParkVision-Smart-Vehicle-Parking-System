<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin   = require_admin_login();
$pdo     = db();
$errors  = [];

/* Only car / bike / van */
$TYPES    = ['car', 'bike', 'van'];
$STATUSES = ['available', 'reserved', 'occupied', 'maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code     = strtoupper(clean_input($_POST['slot_code'] ?? ''));
        $zone     = clean_input($_POST['zone'] ?? 'General');
        $type     = in_array($_POST['slot_type'] ?? '', $TYPES) ? $_POST['slot_type'] : 'car';
        $rate     = (float)($_POST['hourly_rate'] ?? 0);
        $capacity = max(1, (int)($_POST['capacity'] ?? 1));
        if ($type !== 'bike') $capacity = 1; // cars/vans: always 1 per slot

        if ($code === '' || $rate <= 0) {
            set_flash('error', 'Slot code and a valid hourly rate are required.');
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO parking_slots (slot_code, zone, slot_type, vehicle_types, capacity, hourly_rate)
                     VALUES (?, ?, ?, ?, ?, ?)'
                )->execute([$code, $zone, $type, $type, $capacity, $rate]);
                log_activity('admin', $admin['admin_id'], 'slot_create', "Created slot $code ($type, cap:$capacity)");
                set_flash('success', "Slot $code added successfully.");
            } catch (PDOException $e) {
                set_flash('error', $e->errorInfo[1] === 1062 ? "Slot code \"$code\" already exists." : 'Could not add slot.');
            }
        }

    } elseif ($action === 'edit') {
        $slotId   = (int)($_POST['slot_id'] ?? 0);
        $zone     = clean_input($_POST['zone'] ?? 'General');
        $type     = in_array($_POST['slot_type'] ?? '', $TYPES) ? $_POST['slot_type'] : 'car';
        $rate     = (float)($_POST['hourly_rate'] ?? 0);
        $capacity = max(1, (int)($_POST['capacity'] ?? 1));
        $status   = in_array($_POST['status'] ?? '', $STATUSES) ? $_POST['status'] : 'available';
        $notes    = clean_input($_POST['notes'] ?? '');
        if ($type !== 'bike') $capacity = 1;

        $pdo->prepare(
            'UPDATE parking_slots SET zone=?, slot_type=?, vehicle_types=?, capacity=?, hourly_rate=?, status=?, notes=?
             WHERE slot_id=?'
        )->execute([$zone, $type, $type, $capacity, $rate, $status, $notes, $slotId]);
        log_activity('admin', $admin['admin_id'], 'slot_update', "Updated slot ID $slotId");
        set_flash('success', 'Slot updated.');

    } elseif ($action === 'delete') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM parking_slots WHERE slot_id=?')->execute([$slotId]);
            log_activity('admin', $admin['admin_id'], 'slot_delete', "Deleted slot $slotId");
            set_flash('success', 'Slot deleted.');
        } catch (PDOException $e) {
            set_flash('error', 'Cannot delete — slot has linked records. Set status to Maintenance instead.');
        }
    }
    redirect(BASE_URL . 'admin/manage_slots.php');
}

/* Fetch with live occupancy */
$slots = $pdo->query(
    "SELECT slot_id, slot_code, zone, slot_type, capacity, occupied_count, hourly_rate, status, notes
     FROM parking_slots
     ORDER BY zone, slot_code"
)->fetchAll();

/* Summary counts */
$total     = count($slots);
$available = count(array_filter($slots, fn($s) => $s['status'] === 'available'));
$occupied  = count(array_filter($slots, fn($s) => $s['status'] === 'occupied'));
$maintenance = count(array_filter($slots, fn($s) => $s['status'] === 'maintenance'));

$pageTitle = 'Manage Slots';
$activeNav = 'slots';
include __DIR__ . '/includes/header.php';
?>

<style>
.slot-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: .9rem; margin-bottom: 1.4rem; }
.sum-card { background: var(--pv-card); border: 1px solid rgba(255,255,255,.06); border-radius: .85rem; padding: 1.1rem 1.3rem; }
.sum-card .num { font-size: 1.7rem; font-weight: 900; color: #fff; }
.sum-card .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: #7691c0; margin-top: .15rem; }
.sum-card.avail  { border-top: 3px solid #22c55e; }
.sum-card.occ    { border-top: 3px solid #ef4444; }
.sum-card.maint  { border-top: 3px solid #f59e0b; }
.sum-card.tot    { border-top: 3px solid #38bdf8; }

/* Type badge */
.type-car  { background:rgba(37,99,235,.16);  color:#93c5fd; }
.type-bike { background:rgba(245,158,11,.16); color:#fcd34d; }
.type-van  { background:rgba(139,92,246,.16); color:#c4b5fd; }
.type-badge { font-size:.73rem; font-weight:700; padding:.25rem .65rem; border-radius:50rem; white-space:nowrap; }

/* Sub-slot pills for bikes */
.sub-pill {
  display:inline-flex; align-items:center; gap:.25rem;
  font-size:.68rem; font-weight:700; font-family:monospace;
  padding:.18rem .5rem; border-radius:.35rem; margin:.1rem;
}
.sub-pill.free { background:rgba(34,197,94,.15);  color:#4ade80; border:1px solid rgba(34,197,94,.25); }
.sub-pill.taken{ background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.25); }

.cap-bar { height:6px; background:rgba(255,255,255,.08); border-radius:3px; overflow:hidden; margin-top:.35rem; }
.cap-fill{ height:100%; border-radius:3px; transition:width .3s; }
</style>

<!-- Summary strip -->
<div class="slot-summary">
  <div class="sum-card tot" ><div class="num"><?= $total ?></div><div class="lbl">Total Slots</div></div>
  <div class="sum-card avail"><div class="num"><?= $available ?></div><div class="lbl">Available</div></div>
  <div class="sum-card occ">  <div class="num"><?= $occupied ?></div><div class="lbl">Occupied</div></div>
  <div class="sum-card maint"><div class="num"><?= $maintenance ?></div><div class="lbl">Maintenance</div></div>
</div>

<!-- Header row -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2" style="color:var(--pv-cyan);"></i>Parking Slots</h5>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-lg me-1"></i>Add Slot
  </button>
</div>

<!-- Slots table -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-compact table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Code</th>
            <th>Zone</th>
            <th>Type</th>
            <th>Capacity / Sub-slots</th>
            <th>Occupancy</th>
            <th>Rate/hr</th>
            <th>Status</th>
            <th>Notes</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($slots as $s):
            $isBike   = $s['slot_type'] === 'bike';
            $cap      = (int)$s['capacity'];
            $occ      = (int)$s['occupied_count'];
            $free     = $cap - $occ;
            $fillPct  = $cap > 0 ? round($occ/$cap*100) : 0;
            $fillColor = $fillPct >= 100 ? '#ef4444' : ($fillPct >= 60 ? '#f59e0b' : '#22c55e');

            /* Sub-slot letters */
            $subSlotHtml = '';
            if ($isBike) {
              // Fetch taken sub-slots for this slot
              $taken = $pdo->prepare("SELECT sub_slot FROM parking_records WHERE slot_id=? AND status='in_progress' AND sub_slot IS NOT NULL");
              $taken->execute([(int)$s['slot_id']]);
              $takenLetters = array_column($taken->fetchAll(), 'sub_slot');
              for ($i = 0; $i < $cap; $i++) {
                $letter = chr(65+$i);
                $cls = in_array($letter, $takenLetters) ? 'taken' : 'free';
                $subSlotHtml .= "<span class=\"sub-pill $cls\">{$s['slot_code']}$letter</span>";
              }
            }
          ?>
          <tr>
            <td><strong style="font-family:monospace;font-size:.9rem;"><?= e($s['slot_code']) ?></strong></td>
            <td><?= e($s['zone']) ?></td>
            <td>
              <?php
                $typeCls = ['car'=>'type-car','bike'=>'type-bike','van'=>'type-van'];
                $typeIcon= ['car'=>'🚗','bike'=>'🏍️','van'=>'🚐'];
              ?>
              <span class="type-badge <?= $typeCls[$s['slot_type']] ?? '' ?>">
                <?= $typeIcon[$s['slot_type']] ?? '' ?> <?= ucfirst($s['slot_type']) ?>
              </span>
            </td>
            <td style="min-width:180px;">
              <?php if ($isBike): ?>
                <?= $subSlotHtml ?>
                <div class="cap-bar mt-1"><div class="cap-fill" style="width:<?= $fillPct ?>%;background:<?= $fillColor ?>;"></div></div>
              <?php else: ?>
                <span style="color:#8aa0c4;font-size:.82rem;">Single vehicle</span>
              <?php endif; ?>
            </td>
            <td style="min-width:120px;">
              <div style="font-size:.82rem;font-weight:600;">
                <?= $occ ?> / <?= $cap ?> <?= $isBike ? 'bikes' : ($s['slot_type'].'s') ?>
              </div>
              <?php if ($isBike): ?>
                <div class="cap-bar"><div class="cap-fill" style="width:<?= $fillPct ?>%;background:<?= $fillColor ?>;"></div></div>
              <?php endif; ?>
            </td>
            <td>Rs. <?= number_format((float)$s['hourly_rate'], 2) ?></td>
            <td>
              <?php
                $sc = ['available'=>'success','occupied'=>'danger','reserved'=>'warning','maintenance'=>'secondary'];
              ?>
              <span class="badge bg-<?= $sc[$s['status']] ?? 'secondary' ?>"><?= ucfirst($s['status']) ?></span>
            </td>
            <td style="max-width:120px;"><small class="text-muted"><?= e($s['notes'] ?? '') ?></small></td>
            <td>
              <button class="btn btn-sm btn-outline-secondary"
                onclick='openEdit(<?= json_encode($s) ?>)'
                title="Edit"><i class="bi bi-pencil"></i></button>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete slot <?= e($s['slot_code']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="slot_id" value="<?= $s['slot_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── ADD SLOT MODAL ── -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2" style="color:var(--pv-cyan);"></i>Add New Slot</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Slot Code *</label>
              <input type="text" name="slot_code" class="form-control text-uppercase" placeholder="e.g. A-11" required maxlength="20">
              <div class="form-text">Use A-01 for cars, A-B1 for bikes, B-V1 for vans</div>
            </div>
            <div class="col-6">
              <label class="form-label">Zone *</label>
              <input type="text" name="zone" class="form-control" placeholder="Zone A" required>
            </div>
            <div class="col-6">
              <label class="form-label">Vehicle Type *</label>
              <select name="slot_type" id="addSlotType" class="form-select" onchange="toggleCapacity(this,'addCapRow')">
                <option value="car">🚗 Car</option>
                <option value="bike">🏍️ Bike</option>
                <option value="van">🚐 Van</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Hourly Rate (Rs.) *</label>
              <input type="number" name="hourly_rate" class="form-control" step="0.01" min="1" placeholder="100.00" required>
            </div>
            <div class="col-12" id="addCapRow" style="display:none;">
              <label class="form-label">Bike Capacity (max bikes per slot)</label>
              <input type="number" name="capacity" class="form-control" min="1" max="26" value="10" placeholder="10">
              <div class="form-text">Each bike gets a unique sub-slot: A-B1A, A-B1B … up to 26 per slot.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Slot</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── EDIT SLOT MODAL ── -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2" style="color:var(--pv-cyan);"></i>Edit Slot <span id="editSlotCode"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="slot_id" id="editSlotId">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Zone *</label>
              <input type="text" name="zone" id="editZone" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label">Vehicle Type *</label>
              <select name="slot_type" id="editSlotType" class="form-select" onchange="toggleCapacity(this,'editCapRow')">
                <option value="car">🚗 Car</option>
                <option value="bike">🏍️ Bike</option>
                <option value="van">🚐 Van</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Hourly Rate (Rs.) *</label>
              <input type="number" name="hourly_rate" id="editRate" class="form-control" step="0.01" min="1" required>
            </div>
            <div class="col-6">
              <label class="form-label">Status</label>
              <select name="status" id="editStatus" class="form-select">
                <option value="available">Available</option>
                <option value="maintenance">Maintenance</option>
                <option value="occupied">Occupied</option>
                <option value="reserved">Reserved</option>
              </select>
            </div>
            <div class="col-12" id="editCapRow">
              <label class="form-label">Bike Capacity</label>
              <input type="number" name="capacity" id="editCapacity" class="form-control" min="1" max="26" value="10">
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <input type="text" name="notes" id="editNotes" class="form-control" placeholder="Optional">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEdit(s) {
  document.getElementById('editSlotId').value   = s.slot_id;
  document.getElementById('editSlotCode').textContent = s.slot_code;
  document.getElementById('editZone').value     = s.zone;
  document.getElementById('editSlotType').value = s.slot_type;
  document.getElementById('editRate').value     = s.hourly_rate;
  document.getElementById('editStatus').value   = s.status;
  document.getElementById('editCapacity').value = s.capacity || 1;
  document.getElementById('editNotes').value    = s.notes || '';
  toggleCapacity(document.getElementById('editSlotType'), 'editCapRow');
  new bootstrap.Modal(document.getElementById('editModal')).show();
}

function toggleCapacity(sel, rowId) {
  document.getElementById(rowId).style.display = sel.value === 'bike' ? 'block' : 'none';
}

// Init
toggleCapacity(document.getElementById('addSlotType'), 'addCapRow');
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
