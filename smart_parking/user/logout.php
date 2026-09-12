<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Clear remember-me token from DB if set
if (!empty($_SESSION['user_id']) && !empty($_COOKIE[REMEMBER_COOKIE])) {
    db()->prepare('UPDATE users SET remember_token = NULL WHERE user_id = ?')->execute([(int)$_SESSION['user_id']]);
    setcookie(REMEMBER_COOKIE, '', time() - 3600, '/', '', false, true);
}

session_destroy();
redirect(BASE_URL . 'user/login.php');
