<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Either a logged-in user or a logged-in admin may view live slot status.
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$pdo = db();

$slots = $pdo->query(
    'SELECT slot_id, slot_code, zone, slot_type, status, hourly_rate FROM parking_slots ORDER BY slot_code'
)->fetchAll();

$counts = ['available' => 0, 'reserved' => 0, 'occupied' => 0, 'maintenance' => 0];
foreach ($slots as $s) {
    if (isset($counts[$s['status']])) {
        $counts[$s['status']]++;
    }
}

$today = date('Y-m-d');

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM reservations WHERE booking_date = ?");
$stmt->execute([$today]);
$todaysBookings = (int) $stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM parking_records WHERE DATE(entry_time) = ?");
$stmt->execute([$today]);
$todaysEntries = (int) $stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM parking_records WHERE DATE(exit_time) = ?");
$stmt->execute([$today]);
$todaysExits = (int) $stmt->fetch()['c'];

$vehiclesInside = (int) $pdo->query(
    "SELECT COUNT(*) c FROM parking_records WHERE status = 'in_progress'"
)->fetch()['c'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM payments WHERE DATE(paid_at) = ? AND payment_status = 'paid'");
$stmt->execute([$today]);
$totalRevenue = (float) $stmt->fetch()['s'];

$activity = $pdo->query(
    "SELECT log_id, actor_type, action, description, created_at
     FROM activity_logs ORDER BY log_id DESC LIMIT 15"
)->fetchAll();

echo json_encode([
    'success' => true,
    'slots' => $slots,
    'counts' => $counts,
    'total_slots' => count($slots),
    'todays_bookings' => $todaysBookings,
    'todays_entries' => $todaysEntries,
    'todays_exits' => $todaysExits,
    'vehicles_inside' => $vehiclesInside,
    'total_revenue' => $totalRevenue,
    'activity' => $activity,
    'server_time' => date('Y-m-d H:i:s'),
]);
