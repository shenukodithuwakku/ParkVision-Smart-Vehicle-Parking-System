<?php
/**
 * Vehicle Exit API — v2
 * - Decrements occupied_count on exit (sub-slot aware)
 * - Returns slot to 'available' when count drops below capacity
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/qr.php';
require_once __DIR__ . '/../includes/device_auth.php';
require_once __DIR__ . '/../includes/sms.php';

require_device_key_or_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}

$body          = json_body();
$qrToken       = clean_input($body['qr_token'] ?? ($_POST['qr_token'] ?? ''));
$paymentMethod = clean_input($body['payment_method'] ?? ($_POST['payment_method'] ?? 'cash'));
$transactionRef = clean_input($body['transaction_ref'] ?? ($_POST['transaction_ref'] ?? '')) ?: null;

if ($qrToken === '') {
    json_response(['success' => false, 'message' => 'No QR token provided.']);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Find QR code
    $stmt = $pdo->prepare('SELECT qr_id, is_used FROM qr_codes WHERE code_value=? FOR UPDATE');
    $stmt->execute([$qrToken]);
    $qr = $stmt->fetch();

    if (!$qr) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'QR token not recognised. Please check and try again.']);
    }
    if ($qr['is_used']) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'This QR token has already been used for exit.']);
    }

    // Find the active parking record (normal flow: vehicle already entered via gate/ANPR)
    $stmt = $pdo->prepare(
        "SELECT pr.record_id, pr.vehicle_id, pr.slot_id, pr.sub_slot,
                pr.entry_time, pr.plate_number_detected,
                v.vehicle_type, v.plate_number, v.owner_phone, u.phone AS user_phone,
                s.slot_code, s.hourly_rate, s.capacity, s.occupied_count
         FROM parking_records pr
         JOIN vehicles v      ON v.vehicle_id = pr.vehicle_id
         JOIN parking_slots s ON s.slot_id    = pr.slot_id
         LEFT JOIN users u    ON u.user_id    = v.user_id
         WHERE pr.qr_id = ? AND pr.status = 'in_progress'
         FOR UPDATE"
    );
    $stmt->execute([$qr['qr_id']]);
    $record = $stmt->fetch();

    $fromReservation = false;
    $reservationRow  = null;

    if (!$record) {
        // Fallback: this QR may belong to a booking that was never scanned at
        // entry (no parking_records row exists yet). Look it up in
        // reservations so exit + payment can still be processed directly.
        $stmt = $pdo->prepare(
            "SELECT r.reservation_id, r.vehicle_id, r.slot_id, r.booking_date, r.arrival_time, r.status,
                    v.vehicle_type, v.plate_number, v.owner_phone, u.phone AS user_phone,
                    s.slot_code, s.hourly_rate, s.capacity, s.occupied_count
             FROM reservations r
             JOIN vehicles v      ON v.vehicle_id = r.vehicle_id
             JOIN parking_slots s ON s.slot_id    = r.slot_id
             LEFT JOIN users u    ON u.user_id    = v.user_id
             WHERE r.qr_id = ? AND r.status = 'confirmed'
             FOR UPDATE"
        );
        $stmt->execute([$qr['qr_id']]);
        $reservationRow = $stmt->fetch();

        if (!$reservationRow) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'No active session or booking found for this QR code.']);
        }
        $fromReservation = true;
    }

    if ($fromReservation) {
        // Entry was never scanned at the gate — treat the reservation's
        // arrival time as the start of the parking session.
        $entry = new DateTime($reservationRow['booking_date'] . ' ' . $reservationRow['arrival_time']);
        $exit  = new DateTime();
        if ($exit < $entry) { $exit = clone $entry; } // arrived early / exiting right away
        $minutes = max(1, (int)round(($exit->getTimestamp() - $entry->getTimestamp()) / 60));
        $discountPercent = get_loyalty_discount_percent($pdo, (int)$reservationRow['vehicle_id']);
        $fee     = calculate_fee($minutes, (float)$reservationRow['hourly_rate'], $discountPercent);

        // Create the parking record directly in a completed state.
        $pdo->prepare(
            "INSERT INTO parking_records
             (reservation_id, vehicle_id, slot_id, sub_slot, qr_id, plate_number_detected, entry_time, exit_time, duration_minutes, fee_amount, status)
             VALUES (?, ?, ?, NULL, ?, ?, ?, NOW(), ?, ?, 'completed')"
        )->execute([
            (int)$reservationRow['reservation_id'], (int)$reservationRow['vehicle_id'], (int)$reservationRow['slot_id'],
            $qr['qr_id'], $reservationRow['plate_number'], $entry->format('Y-m-d H:i:s'), $minutes, $fee,
        ]);
        $recordId = (int) $pdo->lastInsertId();

        // Mark reservation completed
        $pdo->prepare("UPDATE reservations SET status='completed' WHERE reservation_id=?")
            ->execute([(int)$reservationRow['reservation_id']]);

        // Mark QR used
        $pdo->prepare("UPDATE qr_codes SET is_used=1, used_at=NOW() WHERE qr_id=?")
            ->execute([$qr['qr_id']]);

        // Record payment
        $pdo->prepare(
            "INSERT INTO payments (record_id, amount, payment_method, payment_status, transaction_ref)
             VALUES (?, ?, ?, 'paid', ?)"
        )->execute([$recordId, $fee, $paymentMethod, $transactionRef]);

        // The slot was never marked occupied for this booking (only 'reserved'),
        // so just release it back to available (unless other vehicles occupy it).
        $occupied  = (int)$reservationRow['occupied_count'];
        $newStatus = ($occupied > 0) ? 'occupied' : 'available';
        $pdo->prepare(
            "UPDATE parking_slots SET status=? WHERE slot_id=? AND status='reserved'"
        )->execute([$newStatus, (int)$reservationRow['slot_id']]);

        $record = [
            'plate_number' => $reservationRow['plate_number'],
            'vehicle_type' => $reservationRow['vehicle_type'],
            'slot_code'    => $reservationRow['slot_code'],
            'sub_slot'     => null,
            'capacity'     => (int)$reservationRow['capacity'],
            'entry_time'   => $entry->format('Y-m-d H:i:s'),
        ];
        $notifyPhone = $reservationRow['user_phone'] ?: $reservationRow['owner_phone'];
    } else {
        // Calculate fee
        $entry   = new DateTime($record['entry_time']);
        $exit    = new DateTime();
        $minutes = max(1, (int)round(($exit->getTimestamp() - $entry->getTimestamp()) / 60));
        $discountPercent = get_loyalty_discount_percent($pdo, (int)$record['vehicle_id']);
        $fee     = calculate_fee($minutes, (float)$record['hourly_rate'], $discountPercent);

        // Update parking record
        $pdo->prepare(
            "UPDATE parking_records
             SET exit_time=NOW(), duration_minutes=?, fee_amount=?, status='completed'
             WHERE record_id=?"
        )->execute([$minutes, $fee, (int)$record['record_id']]);

        // Mark QR used
        $pdo->prepare("UPDATE qr_codes SET is_used=1, used_at=NOW() WHERE qr_id=?")
            ->execute([$qr['qr_id']]);

        // Record payment
        $pdo->prepare(
            "INSERT INTO payments (record_id, amount, payment_method, payment_status, transaction_ref)
             VALUES (?, ?, ?, 'paid', ?)"
        )->execute([(int)$record['record_id'], $fee, $paymentMethod, $transactionRef]);

        // Decrement occupied_count, update slot status
        $newOccupied = max(0, ((int)$record['occupied_count']) - 1);
        $newStatus   = ($newOccupied >= (int)$record['capacity']) ? 'occupied' : 'available';
        $pdo->prepare(
            "UPDATE parking_slots SET occupied_count=?, status=? WHERE slot_id=?"
        )->execute([$newOccupied, $newStatus, (int)$record['slot_id']]);

        $notifyPhone = $record['user_phone'] ?: $record['owner_phone'];
    }

    $pdo->commit();

    log_activity('system', null, 'vehicle_exit',
        "Plate {$record['plate_number']} exited slot {$record['slot_code']} sub:{$record['sub_slot']} fee Rs.$fee");

    $h   = (int)floor($minutes / 60);
    $m   = $minutes % 60;
    $dur = $h > 0 ? "{$h}h {$m}min" : "{$m} min";

    $capacity = (int)$record['capacity'];
    $displaySlot = ($capacity > 1 && $record['sub_slot'])
        ? $record['slot_code'] . $record['sub_slot']
        : $record['slot_code'];

    // Best-effort SMS receipt (no-op unless SMS_ENABLED is true).
    if (!empty($notifyPhone)) {
        $feeMsg = 'Rs. ' . number_format($fee, 2);
        notify_sms_best_effort(
            $notifyPhone,
            "Vehicle {$record['plate_number']} exited slot $displaySlot. Duration: $dur. Fee: $feeMsg. Thank you!"
        );
    }

    json_response([
        'success' => true,
        'receipt' => [
            'plate_number'   => $record['plate_number'],
            'vehicle_type'   => $record['vehicle_type'],
            'slot_code'      => $record['slot_code'],
            'sub_slot'       => $record['sub_slot'],
            'display_slot'   => $displaySlot,
            'entry_time'     => $record['entry_time'],
            'exit_time'      => $exit->format('Y-m-d H:i:s'),
            'duration_minutes'=> $minutes,
            'duration_str'   => $dur,
            'payment_method' => $paymentMethod,
            'transaction_ref'=> $transactionRef,
            'fee_amount'     => $fee,
            'fee_formatted'  => 'Rs. ' . number_format($fee, 2),
            'loyalty_discount_percent' => $discountPercent,
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Exit error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error during exit.'], 500);
}
