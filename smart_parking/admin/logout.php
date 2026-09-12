<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!empty($_SESSION['admin_id'])) {
    log_activity('admin', (int) $_SESSION['admin_id'], 'logout', 'Admin logged out');
}

unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
regenerate_session();
set_flash('success', 'You have been logged out of the admin panel.');
redirect(BASE_URL . 'admin/login.php');
