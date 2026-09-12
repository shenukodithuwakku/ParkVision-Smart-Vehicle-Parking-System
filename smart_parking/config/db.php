<?php
/**
 * PDO database connection (singleton).
 * Always use this $pdo handle with prepared statements — never
 * concatenate user input directly into SQL.
 */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Critical: keep MySQL's clock (NOW(), CURRENT_TIMESTAMP) in sync
            // with PHP's date_default_timezone_set(APP_TIMEZONE). Without this,
            // a row timestamped by MySQL (e.g. entry_time via NOW()) and one
            // timestamped by PHP (e.g. exit_time via `new DateTime()`) can
            // differ by hours whenever the DB server's SYSTEM timezone isn't
            // already Asia/Colombo — silently corrupting parking-duration and
            // fee calculations. A numeric UTC offset works even when MySQL's
            // named-timezone tables haven't been loaded (the common case on
            // a stock WAMP install).
            $offset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
            $pdo->exec("SET time_zone = " . $pdo->quote($offset));
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection failed. Please check WAMP is running and the database "smart_parking_db" has been imported.');
        }
    }

    return $pdo;
}
