<?php
require_once __DIR__ . '/functions.php';

define('REMEMBER_COOKIE', 'sp_remember');

function require_user_login(): array
{
    if (empty($_SESSION['user_id'])) {
        if (!empty($_COOKIE[REMEMBER_COOKIE])) {
            $token = clean_input($_COOKIE[REMEMBER_COOKIE]);
            $stmt = db()->prepare('SELECT user_id, full_name, email, status FROM users WHERE remember_token = ?');
            $stmt->execute([$token]);
            $u = $stmt->fetch();
            if ($u && $u['status'] === 'active') {
                regenerate_session();
                $_SESSION['user_id']    = (int)$u['user_id'];
                $_SESSION['user_name']  = $u['full_name'];
                $_SESSION['user_email'] = $u['email'];
            } else {
                setcookie(REMEMBER_COOKIE, '', time() - 3600, '/', '', false, true);
                set_flash('warning', 'Please log in to continue.');
                redirect(BASE_URL . 'user/login.php');
            }
        } else {
            set_flash('warning', 'Please log in to continue.');
            redirect(BASE_URL . 'user/login.php');
        }
    }
    return [
        'user_id'   => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_name'] ?? '',
        'email'     => $_SESSION['user_email'] ?? '',
    ];
}

function require_admin_login(): array
{
    if (empty($_SESSION['admin_id'])) {
        set_flash('warning', 'Please log in to the admin panel to continue.');
        redirect(BASE_URL . 'admin/login.php');
    }
    return [
        'admin_id'  => $_SESSION['admin_id'],
        'full_name' => $_SESSION['admin_name'] ?? '',
        'role'      => $_SESSION['admin_role'] ?? 'operator',
    ];
}

function is_user_logged_in(): bool  { return !empty($_SESSION['user_id']); }
function is_admin_logged_in(): bool { return !empty($_SESSION['admin_id']); }
function regenerate_session(): void { session_regenerate_id(true); }

/** Call after require_admin_login() to additionally restrict a page to super_admin. */
function require_super_admin(array $admin): void
{
    if (($admin['role'] ?? '') !== 'super_admin') {
        set_flash('danger', 'You need a Super Admin account to access that page.');
        redirect(BASE_URL . 'admin/dashboard.php');
    }
}
