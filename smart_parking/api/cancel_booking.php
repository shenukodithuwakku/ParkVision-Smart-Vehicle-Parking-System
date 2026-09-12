<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid session token.']);
    exit;
}

$userId        = (int) $_SESSION['user_id'];
$reservationId = (int) ($_POST['reservation_id'] ?? 0);

$pdo = db();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM reservations WHERE reservation_id = ? AND user_id = ? FOR UPDATE');
    $stmt->execute([$reservationId, $userId]);
    $res = $stmt->fetch();

    if (!$res) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }
    if ($res['status'] !== 'confirmed' && $res['status'] !== 'pending') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This booking can no longer be cancelled.']);
        exit;
    }

    $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?")->execute([$reservationId]);

    // Only flip the slot back to Available if it's still sitting Reserved
    // because of this booking (it won't be if the vehicle already entered,
    // in which case it's Occupied and exit handles the rest).
    $pdo->prepare(
        "UPDATE parking_slots SET status = 'available' WHERE slot_id = ? AND status = 'reserved'"
    )->execute([$res['slot_id']]);

    $pdo->commit();

    log_activity('user', $userId, 'booking_cancelled', "Reservation #$reservationId cancelled by user");
    echo json_encode(['success' => true, 'message' => 'Booking cancelled.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Cancel booking error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
