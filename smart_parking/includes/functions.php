<?php
require_once __DIR__ . '/../config/db.php';

/** Escape output for safe HTML rendering (XSS protection). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Trim + strip tags from raw input before validation. */
function clean_input(?string $value): string
{
    return trim(strip_tags($value ?? ''));
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/** One-time flash messages stored in session, shown once then cleared. */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Read a value from system_settings, with a fallback default. */
function get_setting(string $key, $default = null)
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ? $row['setting_value'] : $default;
    return $cache[$key];
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

/** Write a row to activity_logs. actor_type: admin | user | system */
function log_activity(string $actorType, ?int $actorId, string $action, string $description = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (actor_type, actor_id, action, description, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$actorType, $actorId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        error_log('Failed to write activity log: ' . $e->getMessage());
    }
}

/** Generate a random opaque token used as the payload encoded inside a QR image. */
function generate_qr_token(): string
{
    return bin2hex(random_bytes(20)); // 40 hex chars
}

/** Compute parking fee given minutes parked and an hourly rate. Minimum 1 hour billing.
 *  $discountPercent (0-100) is applied after the hourly calculation, e.g. for
 *  frequent-parker loyalty discounts — see get_loyalty_discount_percent(). */
function calculate_fee(int $minutes, float $hourlyRate, float $discountPercent = 0.0): float
{
    $hours = max(1, ceil($minutes / 60));
    $fee   = $hours * $hourlyRate;
    if ($discountPercent > 0) {
        $fee -= $fee * (min($discountPercent, 100) / 100);
    }
    return round(max(0, $fee), 2);
}

/**
 * Frequent-parker loyalty discount, based on how many times this vehicle has
 * completed a paid parking session before. Tiers are intentionally simple —
 * adjust the thresholds/percentages below to fit your pricing policy.
 */
function get_loyalty_discount_percent(PDO $pdo, int $vehicleId): float
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM parking_records WHERE vehicle_id = ? AND status = 'completed'"
    );
    $stmt->execute([$vehicleId]);
    $visits = (int) $stmt->fetchColumn();

    if ($visits >= 30) return 15.0;
    if ($visits >= 15) return 10.0;
    if ($visits >= 5)  return 5.0;
    return 0.0;
}

/**
 * Vehicles currently parked (status = in_progress) for longer than
 * OVERSTAY_ALERT_HOURS. Used to flag possible abandoned/overstaying
 * vehicles on the admin dashboard and exit screen.
 */
function get_overstay_vehicles(PDO $pdo, int $hours): array
{
    $stmt = $pdo->prepare(
        "SELECT pr.record_id, v.plate_number, v.vehicle_type, s.slot_code, pr.sub_slot,
                pr.entry_time, TIMESTAMPDIFF(MINUTE, pr.entry_time, NOW()) AS minutes_parked
         FROM parking_records pr
         JOIN vehicles v      ON v.vehicle_id = pr.vehicle_id
         JOIN parking_slots s ON s.slot_id    = pr.slot_id
         WHERE pr.status = 'in_progress' AND pr.entry_time <= (NOW() - INTERVAL ? HOUR)
         ORDER BY pr.entry_time ASC"
    );
    $stmt->execute([$hours]);
    return $stmt->fetchAll();
}

function format_money(float $amount): string
{
    $symbol = get_setting('currency_symbol', APP_CURRENCY_SYMBOL);
    return $symbol . ' ' . number_format($amount, 2);
}

/** Basic email format validation. */
function is_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Normalize a license-plate string for consistent lookups (uppercase, no spaces/dashes). */
function normalize_plate(string $plate): string
{
    return strtoupper(preg_replace('/[\s\-]/', '', $plate));
}
