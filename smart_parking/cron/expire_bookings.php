<?php
/**
 * expire_bookings.php
 * --------------------
 * Finds confirmed reservations whose grace period has elapsed (the
 * vehicle never arrived) and expires them, releasing the slot back
 * to "available". Meant to be run every few minutes by Windows Task
 * Scheduler (or cron on Linux/macOS):
 *
 *   "C:\wamp64\bin\php\phpX.Y.Z\php.exe" "C:\wamp64\www\smart_parking\cron\expire_bookings.php"
 *
 * Schedule it to run every 1-5 minutes. Safe to run concurrently with
 * the web app — each reservation is row-locked while it's processed.
 */

// Allow running from the CLI where $_SERVER['HTTP_HOST'] etc. don't exist.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script may only be run from the command line.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();

$stmt = $pdo->query(
    "SELECT reservation_id FROM reservations
     WHERE status = 'confirmed' AND expires_at IS NOT NULL AND expires_at < NOW()"
);
$ids = array_column($stmt->fetchAll(), 'reservation_id');

if (!$ids) {
    echo "[" . date('Y-m-d H:i:s') . "] No expired reservations found.\n";
    exit(0);
}

$expiredCount = 0;

foreach ($ids as $reservationId) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM reservations WHERE reservation_id = ? FOR UPDATE');
        $stmt->execute([$reservationId]);
        $res = $stmt->fetch();

        // Re-check status/expiry inside the lock in case it changed since the outer SELECT.
        if ($res && $res['status'] === 'confirmed' && strtotime($res['expires_at']) < time()) {
            $pdo->prepare("UPDATE reservations SET status = 'expired' WHERE reservation_id = ?")
                ->execute([$reservationId]);
            $pdo->prepare("UPDATE parking_slots SET status = 'available' WHERE slot_id = ? AND status = 'reserved'")
                ->execute([$res['slot_id']]);
            log_activity('system', null, 'reservation_expired', "Reservation #$reservationId auto-expired (grace period elapsed)");
            $expiredCount++;
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('expire_bookings.php error on reservation ' . $reservationId . ': ' . $e->getMessage());
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Expired $expiredCount reservation(s).\n";
