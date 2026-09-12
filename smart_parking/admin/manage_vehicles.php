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
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);

    if ($action === 'edit') {
        $type = clean_input($_POST['vehicle_type'] ?? 'car');
        $model = clean_input($_POST['model'] ?? '');
        $color = clean_input($_POST['color'] ?? '');
        $pdo->prepare('UPDATE vehicles SET vehicle_type = ?, model = ?, color = ? WHERE vehicle_id = ?')
            ->execute([$type, $model, $color, $vehicleId]);
        log_activity('admin', $admin['admin_id'], 'vehicle_update', "Updated vehicle #$vehicleId");
        set_flash('success', 'Vehicle updated.');
    } elseif ($action === 'delete') {
        try {
            $pdo->prepare('DELETE FROM vehicles WHERE vehicle_id = ?')->execute([$vehicleId]);
            log_activity('admin', $admin['admin_id'], 'vehicle_delete', "Deleted vehicle #$vehicleId");
            set_flash('success', 'Vehicle deleted.');
        } catch (PDOException $e) {
            set_flash('error', 'Cannot delete this vehicle — it has linked parking history.');
        }
    }
    redirect(BASE_URL . 'admin/manage_vehicles.php');
}

$search = clean_input($_GET['q'] ?? '');
$sql = "SELECT v.*, u.full_name AS owner_name,
        (SELECT COUNT(*) FROM parking_records pr WHERE pr.vehicle_id = v.vehicle_id) AS visit_count
        FROM vehicles v LEFT JOIN users u ON u.user_id = v.user_id";
$params = [];
if ($search !== '') {
    $sql .= ' WHERE v.plate_number LIKE ?';
    $params[] = "%$search%";
}
$sql .= ' ORDER BY v.created_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

$pageTitle = 'Vehicles';
$activeNav = 'vehicles';
include __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form class="d-flex gap-2" method="get">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Search plate number" value="<?= e($search) ?>">
      <button class="btn btn-sm btn-primary" type="submit">Search</button>
      <?php if ($search !== ''): ?><a href="manage_vehicles.php" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body table-responsive">
    <table class="table table-compact table-hover align-middle">
      <thead><tr><th>Plate</th><th>Type</th><th>Model</th><th>Color</th><th>Owner</th><th>Visits</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($vehicles as $v): ?>
        <tr>
          <td class="fw-bold"><?= e($v['plate_number']) ?></td>
          <td><?= e(ucfirst($v['vehicle_type'])) ?></td>
          <td><?= e($v['model'] ?? '—') ?></td>
          <td><?= e($v['color'] ?? '—') ?></td>
          <td><?= e($v['owner_name'] ?? 'Walk-in') ?></td>
          <td><?= (int) $v['visit_count'] ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editVehicleModal"
              data-vehicle='<?= e(json_encode($v)) ?>'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this vehicle record?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="vehicle_id" value="<?= (int) $v['vehicle_id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$vehicles): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No vehicles found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="editVehicleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="vehicle_id" id="ev_vehicle_id">
        <div class="modal-header"><h5 class="modal-title">Edit Vehicle <span id="ev_plate"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label small">Type</label>
            <select name="vehicle_type" id="ev_type" class="form-select">
              <?php foreach (['car','bike','van','suv','ev'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label small">Model</label><input name="model" id="ev_model" class="form-control"></div>
          <div class="mb-2"><label class="form-label small">Color</label><input name="color" id="ev_color" class="form-control"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('editVehicleModal').addEventListener('show.bs.modal', (event) => {
  const data = JSON.parse(event.relatedTarget.getAttribute('data-vehicle'));
  document.getElementById('ev_vehicle_id').value = data.vehicle_id;
  document.getElementById('ev_plate').textContent = data.plate_number;
  document.getElementById('ev_type').value = data.vehicle_type;
  document.getElementById('ev_model').value = data.model ?? '';
  document.getElementById('ev_color').value = data.color ?? '';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
