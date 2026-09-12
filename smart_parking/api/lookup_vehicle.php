<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/device_auth.php';

if (!is_admin_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$plate = normalize_plate(clean_input($_GET['plate'] ?? ''));
if (strlen($plate) < 3) {
    json_response(['success' => false, 'message' => 'Plate too short']);
}

$stmt = db()->prepare(
    'SELECT v.vehicle_id, v.plate_number, v.vehicle_type, v.model, v.color,
            u.user_id, u.full_name, u.email, u.phone
     FROM vehicles v
     LEFT JOIN users u ON u.user_id = v.user_id
     WHERE v.plate_number = ?'
);
$stmt->execute([$plate]);
$row = $stmt->fetch();

if (!$row) {
    json_response(['success' => true, 'vehicle' => null]);
}

json_response([
    'success' => true,
    'vehicle' => [
        'vehicle_id'   => (int)$row['vehicle_id'],
        'plate_number' => $row['plate_number'],
        'vehicle_type' => $row['vehicle_type'],
        'model'        => $row['model'],
        'color'        => $row['color'],
        'owner'        => $row['user_id'] ? [
            'user_id'   => (int)$row['user_id'],
            'full_name' => $row['full_name'],
            'email'     => $row['email'],
            'phone'     => $row['phone'],
        ] : null,
    ]
]);
