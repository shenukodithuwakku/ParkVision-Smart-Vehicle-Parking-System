<?php
/**
 * Send the QR share link to a vehicle owner's email.
 * Called from admin/vehicle_entry.php after an entry is recorded.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/device_auth.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!is_admin_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$body   = json_body();
$email  = trim($body['email'] ?? ($_POST['email'] ?? ''));
$name   = clean_input($body['name'] ?? ($_POST['name'] ?? ''));
$token  = trim($body['qr_token'] ?? ($_POST['qr_token'] ?? ''));
$plate  = clean_input($body['plate_number'] ?? ($_POST['plate_number'] ?? ''));
$slot   = clean_input($body['slot_label'] ?? ($_POST['slot_label'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'Enter a valid email address.']);
}
if ($token === '') {
    json_response(['success' => false, 'message' => 'Missing QR token — generate the QR first.']);
}

$shareUrl = BASE_URL . 'qr_share.php?token=' . urlencode($token);

$result = send_qr_email($email, $name, $plate, $slot, $shareUrl);

json_response([
    'success' => $result['success'],
    'message' => $result['message'],
]);
