<?php
/**
 * Available Slots API v3
 * - Filters slots by vehicle_type so only matching slots shown
 * - Shows capacity / sub-slot availability for bikes
 * - Shows "A-B1 (7/10 available)" style info
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

$date        = $_GET['date']         ?? '';
$time        = $_GET['time']         ?? '';
$vehicleType = strtolower(trim($_GET['vehicle_type'] ?? 'car'));

// Validate date/time
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date or time format.']);
    exit;
}
$requested = strtotime("$date " . substr($time, 0, 5) . ':00');
if ($requested === false || $requested < time() - 60) {
    echo json_encode(['success' => false, 'message' => 'Please choose a date/time in the future.']);
    exit;
}

// Normalise vehicle type
$allowed = ['car', 'bike', 'van'];
if (!in_array($vehicleType, $allowed)) $vehicleType = 'car';

$pdo = db();

// Get slots that match this vehicle type and have capacity remaining
$slots = $pdo->prepare(
    "SELECT slot_id, slot_code, zone, slot_type, vehicle_types, capacity, occupied_count, hourly_rate, status
     FROM parking_slots
     WHERE FIND_IN_SET(?, REPLACE(vehicle_types, ' ', ''))
     ORDER BY zone, slot_code"
);
$slots->execute([$vehicleType]);
$rows = $slots->fetchAll();

$result = array_map(function ($row) use ($vehicleType) {
    $capacity     = (int)$row['capacity'];
    $occupied     = (int)$row['occupied_count'];
    $remaining    = $capacity - $occupied;
    $isBike       = $row['slot_type'] === 'bike';

    // A slot is bookable if it has at least 1 space free and is not in maintenance
    $bookable = $remaining > 0 && $row['status'] !== 'maintenance' && $row['status'] !== 'occupied';

    // Label: "A-B1 (8 spaces left)" for bikes, plain code for cars/vans
    $label = $isBike
        ? "{$row['slot_code']} ({$remaining}/{$capacity} free)"
        : $row['slot_code'];

    return [
        'slot_id'      => (int)$row['slot_id'],
        'slot_code'    => $row['slot_code'],
        'label'        => $label,
        'zone'         => $row['zone'],
        'slot_type'    => $row['slot_type'],
        'capacity'     => $capacity,
        'occupied'     => $occupied,
        'remaining'    => $remaining,
        'hourly_rate'  => (float)$row['hourly_rate'],
        'status'       => $row['status'],
        'available'    => $bookable,
        'is_shared'    => $capacity > 1,
    ];
}, $rows);

echo json_encode([
    'success'      => true,
    'date'         => $date,
    'time'         => substr($time, 0, 5),
    'vehicle_type' => $vehicleType,
    'slots'        => $result,
]);
