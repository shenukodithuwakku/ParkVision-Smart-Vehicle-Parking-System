<?php
/**
 * Endpoints under /api/ that are called by the entry camera (Python)
 * or the exit QR-scanner page authenticate with a shared device key
 * instead of a user session, since no human is logged in at the gate.
 *
 * Sent either as header "X-Device-Key" or POST field "device_key".
 */
function require_device_key(): void
{
    $key = $_SERVER['HTTP_X_DEVICE_KEY'] ?? ($_POST['device_key'] ?? '');
    if (!hash_equals(DEVICE_API_KEY, (string) $key)) {
        json_response(['success' => false, 'message' => 'Unauthorized device.'], 401);
    }
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Exit endpoint can be called either by:
 *  (a) the exit scanner device (with X-Device-Key header), OR
 *  (b) an admin browser session.
 */
function require_device_key_or_admin(): void
{
    if (!empty($_SESSION['admin_id'])) {
        return; // admin session — OK
    }
    $key = $_SERVER['HTTP_X_DEVICE_KEY'] ?? ($_POST['device_key'] ?? '');
    if (!hash_equals(DEVICE_API_KEY, (string)$key)) {
        json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
    }
}
