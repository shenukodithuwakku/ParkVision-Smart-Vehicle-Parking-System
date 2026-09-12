<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin_login();
require_super_admin($admin);

// Simple token check (GET link, not a form post) — must match the current session's CSRF token.
$token = $_GET['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die('Invalid or expired link. Go back to the Backup page and click Download again.');
}

$pdo = db();
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

$filename = 'smart_parking_backup_' . date('Y-m-d_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo "-- ParkVision database backup\n";
echo "-- Database: {$dbName}\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $tableQuoted = "`" . str_replace("`", "``", $table) . "`";

    echo "-- ----------------------------\n-- Table: {$table}\n-- ----------------------------\n";
    echo "DROP TABLE IF EXISTS {$tableQuoted};\n";

    $createRow = $pdo->query("SHOW CREATE TABLE {$tableQuoted}")->fetch(PDO::FETCH_ASSOC);
    echo $createRow['Create Table'] . ";\n\n";

    $rowCount = (int) $pdo->query("SELECT COUNT(*) FROM {$tableQuoted}")->fetchColumn();
    if ($rowCount === 0) {
        continue;
    }

    $colStmt = $pdo->query("SHOW COLUMNS FROM {$tableQuoted}");
    $columns = array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $colList = implode(', ', array_map(fn($c) => "`" . str_replace("`", "``", $c) . "`", $columns));

    $chunkSize = 500;
    $offset = 0;
    while (true) {
        $rows = $pdo->query("SELECT * FROM {$tableQuoted} LIMIT {$chunkSize} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            break;
        }
        $valueGroups = [];
        foreach ($rows as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string) $v);
            }, array_values($row));
            $valueGroups[] = '(' . implode(', ', $vals) . ')';
        }
        echo "INSERT INTO {$tableQuoted} ({$colList}) VALUES\n" . implode(",\n", $valueGroups) . ";\n";
        $offset += $chunkSize;
        if (count($rows) < $chunkSize) {
            break;
        }
    }
    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";

log_activity('admin', $admin['admin_id'], 'db_backup', "Downloaded backup of {$dbName} (" . count($tables) . " tables)");
