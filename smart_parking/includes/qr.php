<?php
require_once __DIR__ . '/functions.php';

/**
 * Create a new row in qr_codes and return its [qr_id, token].
 * The token is the literal text encoded into the QR image
 * (rendered client-side by assets/js/qrcode-render.js).
 */
function issue_qr_code(): array
{
    $token = generate_qr_token();
    $stmt = db()->prepare('INSERT INTO qr_codes (code_value) VALUES (?)');
    $stmt->execute([$token]);
    return ['qr_id' => (int) db()->lastInsertId(), 'token' => $token];
}

function find_qr_by_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM qr_codes WHERE code_value = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mark_qr_used(int $qrId): void
{
    $stmt = db()->prepare('UPDATE qr_codes SET is_used = 1, used_at = NOW() WHERE qr_id = ?');
    $stmt->execute([$qrId]);
}

/**
 * One persistent QR per number plate (vehicle).
 * - If this vehicle already has a QR linked (vehicles.qr_id), reuse it —
 *   the driver gets the exact same QR/token every time this plate enters.
 * - Otherwise issue a brand-new QR and permanently link it to the vehicle.
 * Must be called with the same PDO connection/transaction that already
 * holds a lock on the vehicle row (e.g. after "... FOR UPDATE") so two
 * concurrent entries for the same plate can never create two QR codes.
 */
function get_or_create_vehicle_qr(PDO $pdo, int $vehicleId): array
{
    $stmt = $pdo->prepare(
        'SELECT q.qr_id, q.code_value
         FROM vehicles v
         JOIN qr_codes q ON q.qr_id = v.qr_id
         WHERE v.vehicle_id = ?'
    );
    $stmt->execute([$vehicleId]);
    $row = $stmt->fetch();

    if ($row) {
        return ['qr_id' => (int) $row['qr_id'], 'token' => $row['code_value'], 'reused' => true];
    }

    $token = generate_qr_token();
    $pdo->prepare('INSERT INTO qr_codes (code_value) VALUES (?)')->execute([$token]);
    $qrId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE vehicles SET qr_id = ? WHERE vehicle_id = ?')->execute([$qrId, $vehicleId]);

    return ['qr_id' => $qrId, 'token' => $token, 'reused' => false];
}
