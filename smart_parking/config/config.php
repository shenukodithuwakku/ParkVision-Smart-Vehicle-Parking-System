<?php
/**
 * Global configuration.
 * Defaults match a stock WAMP install (Apache + MySQL on localhost,
 * root user, no password). Change DB_PASS if you set one in WAMP.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_parking_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Application ----
// If the project folder inside www/ is named "smart_parking", leave as is.
define('BASE_URL', 'http://localhost/smart_parking/');
define('APP_NAME', 'ParkVision');
define('APP_TIMEZONE', 'Asia/Colombo');

// ---- Paths ----
define('ROOT_PATH', dirname(__DIR__));
define('QR_UPLOAD_DIR', ROOT_PATH . '/uploads/qr_codes/');
define('PLATE_UPLOAD_DIR', ROOT_PATH . '/uploads/plates/');
define('LOG_DIR', ROOT_PATH . '/logs/');

// ---- Business rules (overridable via system_settings table at runtime) ----
define('DEFAULT_GRACE_PERIOD_MINUTES', 30);
define('DEFAULT_HOURLY_RATE', 100.00);
// A vehicle still parked (never exited) longer than this many hours shows up
// as an "Overstay" alert on the admin dashboard — likely abandoned, or the
// driver forgot to scan out. Adjust to whatever's reasonable for your site.
define('OVERSTAY_ALERT_HOURS', 12);
define('APP_CURRENCY_SYMBOL', 'Rs.');

// ---- Shared secret used by the Python plate-recognition script and the
//      QR scanner page to authenticate against api/entry.php and
//      api/exit.php. Change this to something random before deployment. ----
define('DEVICE_API_KEY', 'CHANGE_THIS_TO_A_RANDOM_SECRET_BEFORE_DEPLOYMENT');

// ---- Email (QR notification) ----
// Works with Gmail SMTP out of the box: turn on 2FA on the Gmail account,
// create an "App Password" (myaccount.google.com/apppasswords), and put
// that 16-character app password below — NOT your normal Gmail password.
// Any other SMTP provider (Outlook, Zoho, your host's mail server, etc.)
// works too — just change host/port/secure to match it.
// Leave SMTP_ENABLED as false until you've filled these in; the app keeps
// working fine without email — the WhatsApp button is unaffected.
define('SMTP_ENABLED',    false);
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);          // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE',     'tls');        // 'tls' | 'ssl'
define('SMTP_USERNAME',   'your-email@gmail.com');
define('SMTP_PASSWORD',   'your-16-char-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME',  APP_NAME);

// ---- SMS (Dialog eSMS) ----
// Sign up for a business account at https://esms.dialog.lk, then find your
// "URL Message Key" and approved sender mask in the account's API/Developer
// settings page. See includes/sms.php for details on adjusting the request
// if your account's exact API format differs from the default below.
// Leave SMS_ENABLED as false until configured — the rest of the app is
// unaffected either way (WhatsApp/Email buttons keep working).
define('SMS_ENABLED',        false);
define('DIALOG_SMS_ENDPOINT','https://e-sms.dialog.lk/api/v1/sms/send');
define('DIALOG_SMS_API_KEY', 'your-esms-url-message-key');
define('DIALOG_SMS_MASK',    'ParkVision');

date_default_timezone_set(APP_TIMEZONE);

// ---- Error display: OFF in production. Flip to 1 only while debugging. ----
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_DIR . 'php_errors.log');

// ---- Session hardening ----
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
// Uncomment when serving over HTTPS:
// ini_set('session.cookie_secure', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
