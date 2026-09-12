<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/qr.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    json_response_local(['success' => false, 'message' => 'Please log in.'], 401);
}
function json_response_local(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response_local(['success' => false, 'message' => 'Invalid request method.'], 405);
}

// CSRF check (token sent as a regular POST field from the AJAX call)
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    json_response_local(['success' => false, 'message' => 'Invalid session token, please refresh the page.'], 403);
}

$userId      = (int) $_SESSION['user_id'];
$slotId      = (int) ($_POST['slot_id'] ?? 0);
$bookingDate = clean_input($_POST['booking_date'] ?? '');
$arrivalTime = clean_input($_POST['arrival_time'] ?? '');
$plateNumber = normalize_plate(clean_input($_POST['plate_number'] ?? ''));
$vehicleType = clean_input($_POST['vehicle_type'] ?? 'car');

// ---- Validation ----
if ($slotId <= 0) {
    json_response_local(['success' => false, 'message' => 'Please select a parking slot.']);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
    json_response_local(['success' => false, 'message' => 'Invalid booking date.']);
}
if (!preg_match('/^\d{2}:\d{2}$/', $arrivalTime)) {
    json_response_local(['success' => false, 'message' => 'Invalid arrival time.']);
}
$arrivalTimestamp = strtotime("$bookingDate $arrivalTime:00");
if ($arrivalTimestamp === false || $arrivalTimestamp < time() - 60) {
    json_response_local(['success' => false, 'message' => 'Please choose a future date/time.']);
}
if ($plateNumber === '' || !preg_match('/^[A-Z0-9]{4,12}$/', $plateNumber)) {
    json_response_local(['success' => false, 'message' => 'Please enter a valid vehicle number plate.']);
}
$allowedTypes = ['car', 'bike', 'van', 'suv', 'ev'];
if (!in_array($vehicleType, $allowedTypes, true)) {
    $vehicleType = 'car';
}

$gracePeriod = (int) get_setting('grace_period_minutes', DEFAULT_GRACE_PERIOD_MINUTES);
$expiresAt   = date('Y-m-d H:i:s', $arrivalTimestamp + $gracePeriod * 60);

$pdo = db();

try {
    $pdo->beginTransaction();

    // 1. Lock the slot row so no concurrent request can read a stale status.
    $stmt = $pdo->prepare('SELECT slot_id, slot_code, status, vehicle_types, capacity, occupied_count FROM parking_slots WHERE slot_id = ? FOR UPDATE');
    $stmt->execute([$slotId]);
    $slot = $stmt->fetch();

    if (!$slot) {
        $pdo->rollBack();
        json_response_local(['success' => false, 'message' => 'Selected slot does not exist.']);
    }

    // ── Vehicle type must match slot type ──
    $allowedForSlot = array_map('trim', explode(',', $slot['vehicle_types']));
    // Map suv/ev to car for slot matching (they use car slots)
    $typeForMatch = in_array($vehicleType, ['suv','ev']) ? 'car' : $vehicleType;
    if (!in_array($typeForMatch, $allowedForSlot)) {
        $pdo->rollBack();
        json_response_local(['success' => false,
            'message' => "This slot is for: " . implode('/', $allowedForSlot) . ". Your vehicle type ($vehicleType) does not match."
        ], 409);
    }

    // ── For bike slots (capacity > 1): check remaining spaces ──
    if ((int)$slot['capacity'] > 1) {
        if ((int)$slot['occupied_count'] >= (int)$slot['capacity']) {
            $pdo->rollBack();
            json_response_local(['success' => false, 'message' => "Slot {$slot['slot_code']} is fully occupied ({$slot['capacity']}/{$slot['capacity']} bikes). Choose another bike slot."], 409);
        }
        // Bike slots can still accept bookings even if partially occupied
    } elseif ($slot['status'] !== 'available') {
        $pdo->rollBack();
        json_response_local(['success' => false, 'message' => 'Sorry, this slot was just taken. Please pick a different slot.'], 409);
    }

    // 2. Find or create the vehicle, linked to this user.
    $stmt = $pdo->prepare('SELECT vehicle_id, user_id FROM vehicles WHERE plate_number = ?');
    $stmt->execute([$plateNumber]);
    $vehicle = $stmt->fetch();

    if ($vehicle) {
        $vehicleId = (int) $vehicle['vehicle_id'];
        if ($vehicle['user_id'] === null) {
            $pdo->prepare('UPDATE vehicles SET user_id = ? WHERE vehicle_id = ?')->execute([$userId, $vehicleId]);
        }
    } else {
        $pdo->prepare('INSERT INTO vehicles (user_id, plate_number, vehicle_type) VALUES (?, ?, ?)')
            ->execute([$userId, $plateNumber, $vehicleType]);
        $vehicleId = (int) $pdo->lastInsertId();
    }

    // 3. QR for this booking — reuse the plate's single persistent QR if it
    //    already has one (from an earlier visit/booking), otherwise issue it
    //    now. This guarantees exactly one QR per number plate, ever.
    $qr = get_or_create_vehicle_qr($pdo, $vehicleId);

    // 4. Insert the reservation. The generated `active_lock` column +
    //    its UNIQUE index gives a hard DB-level guarantee that no two
    //    active reservations can ever exist for this slot/date/time,
    //    even if this PHP-level check were somehow bypassed.
    $stmt = $pdo->prepare(
        'INSERT INTO reservations (user_id, slot_id, vehicle_id, booking_date, arrival_time, grace_period_minutes, expires_at, status, qr_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $slotId, $vehicleId, $bookingDate, $arrivalTime . ':00', $gracePeriod, $expiresAt, 'confirmed', $qr['qr_id']]);
    $reservationId = (int) $pdo->lastInsertId();

    // 5. Flip the slot to Reserved for everyone, instantly.
    $pdo->prepare("UPDATE parking_slots SET status = 'reserved' WHERE slot_id = ?")->execute([$slotId]);

    $pdo->commit();

    log_activity('user', $userId, 'booking_created', "Reservation #$reservationId for slot ID $slotId");

    json_response_local([
        'success' => true,
        'message' => 'Slot booked successfully!',
        'reservation_id' => $reservationId,
        'qr_token' => $qr['token'],
        'expires_at' => $expiresAt,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();

    // 1062 = duplicate key -> the active_lock unique index caught a race condition.
    if ($e->errorInfo[1] === 1062) {
        json_response_local(['success' => false, 'message' => 'This slot was just booked for that exact date/time by someone else.'], 409);
    }

    error_log('Booking error: ' . $e->getMessage());
    json_response_local(['success' => false, 'message' => 'Something went wrong while booking. Please try again.'], 500);
}
