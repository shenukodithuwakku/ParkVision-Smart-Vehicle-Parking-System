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
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);

    if ($action === 'cancel') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM reservations WHERE reservation_id = ? FOR UPDATE');
            $stmt->execute([$reservationId]);
            $res = $stmt->fetch();

            if ($res && in_array($res['status'], ['pending', 'confirmed'], true)) {
                $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?")->execute([$reservationId]);
                $pdo->prepare("UPDATE parking_slots SET status = 'available' WHERE slot_id = ? AND status = 'reserved'")
                    ->execute([$res['slot_id']]);
                log_activity('admin', $admin['admin_id'], 'reservation_cancel', "Admin cancelled reservation #$reservationId");
                set_flash('success', 'Reservation cancelled and slot released.');
            } else {
                set_flash('error', 'This reservation can no longer be cancelled.');
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            set_flash('error', 'Could not cancel reservation.');
        }
    }
    redirect(BASE_URL . 'admin/manage_reservations.php');
}

$statusFilter = $_GET['status'] ?? '';
$dateFilter   = $_GET['date'] ?? '';

$sql = "SELECT r.*, u.full_name, u.email, v.plate_number, s.slot_code
        FROM reservations r
        JOIN users u ON u.user_id = r.user_id
        JOIN parking_slots s ON s.slot_id = r.slot_id
        LEFT JOIN vehicles v ON v.vehicle_id = r.vehicle_id
        WHERE 1=1";
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending','confirmed','cancelled','expired','completed'], true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $statusFilter;
}
if ($dateFilter !== '') {
    $sql .= ' AND r.booking_date = ?';
    $params[] = $dateFilter;
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$pageTitle = 'Reservations';
$activeNav = 'reservations';
include __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-auto">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach (['pending','confirmed','cancelled','expired','completed'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-1">Booking Date</label>
        <input type="date" name="date" value="<?= e($dateFilter) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit">Filter</button>
        <a href="manage_reservations.php" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body table-responsive">
    <table class="table table-compact table-hover align-middle">
      <thead><tr><th>#</th><th>User</th><th>Slot</th><th>Plate</th><th>Date</th><th>Arrival</th><th>Status</th><th>Expires</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($reservations as $r): ?>
        <?php $badge = ['pending'=>'warning','confirmed'=>'primary','cancelled'=>'secondary','expired'=>'danger','completed'=>'success'][$r['status']] ?? 'secondary'; ?>
        <tr>
          <td>#<?= (int) $r['reservation_id'] ?></td>
          <td><?= e($r['full_name']) ?><br><span class="text-muted small"><?= e($r['email']) ?></span></td>
          <td><?= e($r['slot_code']) ?></td>
          <td><?= e($r['plate_number'] ?? '—') ?></td>
          <td><?= e($r['booking_date']) ?></td>
          <td><?= e($r['arrival_time']) ?></td>
          <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($r['status']) ?></span></td>
          <td class="small text-muted"><?= e($r['expires_at']) ?></td>
          <td>
            <?php if (in_array($r['status'], ['pending','confirmed'], true)): ?>
              <form method="post" onsubmit="return confirm('Cancel this reservation and free the slot?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Cancel</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reservations): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No reservations match this filter.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
