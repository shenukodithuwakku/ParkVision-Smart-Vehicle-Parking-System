<?php
/**
 * Vehicle Entry API — v2
 * - Matches vehicle type to slot type
 * - Supports sub-slots (capacity > 1) for bikes
 * - Assigns A/B/C… sub-slot letter per vehicle within a shared slot
 * - One vehicle per sub-slot at a time
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/qr.php';
require_once __DIR__ . '/../includes/device_auth.php';
require_once __DIR__ . '/../includes/sms.php';

require_device_key();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$body       = json_body();
$plateRaw   = clean_input($body['plate_number']  ?? ($_POST['plate_number']  ?? ''));
$typeInput  = strtolower(trim($body['vehicle_type'] ?? ($_POST['vehicle_type'] ?? 'car')));
$ownerPhone = clean_input($body['owner_phone'] ?? ($_POST['owner_phone'] ?? '')) ?: null;
$plate      = normalize_plate($plateRaw);

// Normalise vehicle type to only car/bike/van
$allowed_types = ['car', 'bike', 'van'];
$vehicleType = in_array($typeInput, $allowed_types) ? $typeInput : 'car';

if ($plate === '' || strlen($plate) < 4) {
    json_response(['success' => false, 'message' => 'No valid plate number detected.']);
}

$pdo = db();

/**
 * Get occupied sub-slots for a given slot_id (letters already taken).
 */
function occupied_sub_slots(PDO $pdo, int $slotId): array
{
    $stmt = $pdo->prepare(
        "SELECT sub_slot FROM parking_records
         WHERE slot_id = ? AND status = 'in_progress' AND sub_slot IS NOT NULL"
    );
    $stmt->execute([$slotId]);
    return array_column($stmt->fetchAll(), 'sub_slot');
}

/**
 * Pick next free sub-slot letter for a slot.
 * Returns null if slot is at capacity.
 */
function next_sub_slot(PDO $pdo, int $slotId, int $capacity): ?string
{
    $taken = occupied_sub_slots($pdo, $slotId);
    for ($i = 0; $i < $capacity; $i++) {
        $letter = chr(65 + $i); // A, B, C …
        if (!in_array($letter, $taken)) return $letter;
    }
    return null; // all full
}

try {
    $pdo->beginTransaction();

    // ── 1. Find or create vehicle ──
    $stmt = $pdo->prepare(
        'SELECT v.vehicle_id, v.vehicle_type, v.user_id, v.owner_phone, u.phone AS user_phone
         FROM vehicles v LEFT JOIN users u ON u.user_id = v.user_id
         WHERE v.plate_number = ? FOR UPDATE'
    );
    $stmt->execute([$plate]);
    $vehicle = $stmt->fetch();

    if ($vehicle) {
        $vehicleId = (int) $vehicle['vehicle_id'];
        // Update type if entry form specified one different from stored
        if ($vehicle['vehicle_type'] !== $vehicleType) {
            $pdo->prepare('UPDATE vehicles SET vehicle_type=? WHERE vehicle_id=?')
                ->execute([$vehicleType, $vehicleId]);
        }
        // Fill in a contact number if we didn't have one yet (never overwrite an existing one here).
        if ($ownerPhone && empty($vehicle['owner_phone']) && empty($vehicle['user_id'])) {
            $pdo->prepare('UPDATE vehicles SET owner_phone=? WHERE vehicle_id=?')
                ->execute([$ownerPhone, $vehicleId]);
        }
        $notifyPhone = $vehicle['user_phone'] ?: ($vehicle['owner_phone'] ?: $ownerPhone);
    } else {
        $pdo->prepare(
            'INSERT INTO vehicles (plate_number, vehicle_type, owner_phone) VALUES (?, ?, ?)'
        )->execute([$plate, $vehicleType, $ownerPhone]);
        $vehicleId = (int) $pdo->lastInsertId();
        $notifyPhone = $ownerPhone;
    }

    // ── 1b. One persistent QR per plate: reuse it if this vehicle already
    //        has one, otherwise issue it now and link it permanently. ──
    $vehicleQr = get_or_create_vehicle_qr($pdo, $vehicleId);

    // ── 2. Block duplicate active session ──
    $dup = $pdo->prepare(
        "SELECT record_id FROM parking_records WHERE vehicle_id=? AND status='in_progress' LIMIT 1"
    );
    $dup->execute([$vehicleId]);
    if ($dup->fetch()) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => "Vehicle $plate already has an active parking session. Please exit first."], 409);
    }

    // ── 3. Check active reservation (type-matched) ──
    $stmt = $pdo->prepare(
        "SELECT r.reservation_id, r.slot_id, r.qr_id, r.expires_at,
                s.slot_code, s.capacity, s.vehicle_types, s.occupied_count
         FROM reservations r
         JOIN parking_slots s ON s.slot_id = r.slot_id
         WHERE r.vehicle_id = ? AND r.status = 'confirmed'
         ORDER BY r.created_at DESC LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$vehicleId]);
    $reservation = $stmt->fetch();

    $slotId       = null;
    $slotCode     = null;
    $capacity     = 1;
    $subSlot      = null;
    $qrId         = null;
    $reservationId = null;
    $mode         = '';

    if ($reservation && strtotime($reservation['expires_at']) >= time()) {
        // Validate type match for reservation
        $allowedTypesForSlot = array_map('trim', explode(',', $reservation['vehicle_types']));
        if (!in_array($vehicleType, $allowedTypesForSlot)) {
            $pdo->rollBack();
            json_response(['success' => false,
                'message' => "Your reservation slot only allows: " . implode(', ', $allowedTypesForSlot) . ". Your vehicle type ($vehicleType) does not match."], 409);
        }

        $slotId       = (int) $reservation['slot_id'];
        $slotCode     = $reservation['slot_code'];
        $capacity     = (int) $reservation['capacity'];
        $qrId         = $vehicleQr['qr_id']; // always the plate's single persistent QR
        $reservationId = (int) $reservation['reservation_id'];
        $mode         = 'reserved';

        $subSlot = next_sub_slot($pdo, $slotId, $capacity);
        if ($subSlot === null) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => "Slot $slotCode is already at full capacity."], 409);
        }

        // Update reservation status
        $pdo->prepare("UPDATE reservations SET status='used' WHERE reservation_id=?")->execute([$reservationId]);

    } else {
        // Expired reservation cleanup
        if ($reservation) {
            $pdo->prepare("UPDATE reservations SET status='expired' WHERE reservation_id=?")
                ->execute([$reservation['reservation_id']]);
            // Only free slot if fully empty
            $pdo->prepare(
                "UPDATE parking_slots SET status='available'
                 WHERE slot_id=? AND status='reserved' AND occupied_count=0"
            )->execute([$reservation['slot_id']]);
        }

        // ── Walk-in: find type-matched slot with capacity ──
        // For bike: find a slot of type 'bike' that still has space
        // For car/van: find a slot of matching type that is 'available' (capacity=1, occupied=0)
        $stmt = $pdo->prepare(
            "SELECT slot_id, slot_code, capacity, occupied_count, vehicle_types
             FROM parking_slots
             WHERE status IN ('available','occupied')
               AND FIND_IN_SET(?, REPLACE(vehicle_types, ' ', ''))
               AND occupied_count < capacity
             ORDER BY occupied_count DESC, slot_id ASC
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$vehicleType]);
        $freeSlot = $stmt->fetch();

        if (!$freeSlot) {
            $pdo->rollBack();
            json_response(['success' => false,
                'message' => "No available $vehicleType slots. All $vehicleType parking spaces are full."], 409);
        }

        $slotId   = (int) $freeSlot['slot_id'];
        $slotCode = $freeSlot['slot_code'];
        $capacity = (int) $freeSlot['capacity'];
        $mode     = 'walk_in';

        $subSlot = next_sub_slot($pdo, $slotId, $capacity);
        if ($subSlot === null) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => "Slot $slotCode just filled up. Please retry."], 409);
        }

        $qrId = $vehicleQr['qr_id']; // the plate's single persistent QR (reused if it already exists)
    }

    // ── 4. Increment occupied_count; mark full if needed ──
    $newOccupied = ((int)($freeSlot['occupied_count'] ?? 0)) + 1;
    // Re-fetch for reservation path
    if ($mode === 'reserved') {
        $rc = $pdo->prepare('SELECT occupied_count FROM parking_slots WHERE slot_id=?');
        $rc->execute([$slotId]);
        $newOccupied = ((int)$rc->fetch()['occupied_count']) + 1;
    }
    $newStatus = ($newOccupied >= $capacity) ? 'occupied' : 'available';
    $pdo->prepare(
        "UPDATE parking_slots SET occupied_count=?, status=? WHERE slot_id=?"
    )->execute([$newOccupied, $newStatus, $slotId]);

    // ── 5. Create parking record ──
    $stmt = $pdo->prepare(
        'INSERT INTO parking_records
         (reservation_id, vehicle_id, slot_id, sub_slot, qr_id, plate_number_detected, entry_time, status)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)'
    );
    $stmt->execute([$reservationId, $vehicleId, $slotId, $subSlot, $qrId, $plateRaw, 'in_progress']);
    $recordId = (int) $pdo->lastInsertId();

    $pdo->commit();

    log_activity('system', null, 'vehicle_entry',
        "Plate $plate ($vehicleType) entered slot $slotCode sub-slot $subSlot ($mode)");

    // QR token — the plate's single persistent QR (same token every visit)
    $qrToken = $vehicleQr['token'];

    // Build display slot code: e.g. "A-09B" for bike slot A-09 sub-slot B
    $displaySlot = ($capacity > 1)
        ? $slotCode . $subSlot     // e.g. A-09B
        : $slotCode;               // e.g. A-01 (car/van, no sub-slot needed)

    // Best-effort SMS notification (no-op unless SMS_ENABLED is true).
    if (!empty($notifyPhone)) {
        notify_sms_best_effort(
            $notifyPhone,
            "Your vehicle $plate has entered slot $displaySlot. Your QR: " . BASE_URL . 'qr_share.php?token=' . $qrToken
        );
    }

    json_response([
        'success'      => true,
        'mode'         => $mode,
        'record_id'    => $recordId,
        'slot_id'      => $slotId,
        'slot_code'    => $slotCode,
        'sub_slot'     => $subSlot,
        'display_slot' => $displaySlot,
        'vehicle_type' => $vehicleType,
        'qr_token'     => $qrToken,
        'qr_reused'    => $vehicleQr['reused'],
        'plate_number' => $plate,
        'share_url'    => BASE_URL . 'qr_share.php?token=' . urlencode($qrToken),
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Entry processing error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error while processing entry.'], 500);
}
