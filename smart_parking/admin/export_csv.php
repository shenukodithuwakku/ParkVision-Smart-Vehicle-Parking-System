<?php
/**
 * Streams a CSV file of parking records for the given date range.
 * CSV is chosen over a binary .xlsx so the export has zero external
 * dependencies and works on a stock WAMP install — Excel opens CSV
 * files natively.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-6 day'));
$to   = $_GET['to'] ?? date('Y-m-d');

$stmt = db()->prepare(
    "SELECT pr.record_id, v.plate_number, s.slot_code, pr.entry_time, pr.exit_time,
            pr.duration_minutes, pr.fee_amount, p.payment_method, p.payment_status, pr.status
     FROM parking_records pr
     JOIN vehicles v ON v.vehicle_id = pr.vehicle_id
     JOIN parking_slots s ON s.slot_id = pr.slot_id
     LEFT JOIN payments p ON p.record_id = pr.record_id
     WHERE DATE(pr.entry_time) BETWEEN ? AND ?
     ORDER BY pr.entry_time DESC"
);
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="parking_report_' . $from . '_to_' . $to . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Record ID', 'Plate Number', 'Slot', 'Entry Time', 'Exit Time', 'Duration (min)', 'Fee', 'Payment Method', 'Payment Status', 'Record Status']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['record_id'],
        $r['plate_number'],
        $r['slot_code'],
        $r['entry_time'],
        $r['exit_time'] ?? '',
        $r['duration_minutes'] ?? '',
        $r['fee_amount'] ?? '',
        $r['payment_method'] ?? '',
        $r['payment_status'] ?? '',
        $r['status'],
    ]);
}
fclose($out);
exit;
