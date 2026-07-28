<?php
/**
 * admin.php  (top-level entry point)
 *
 * A convenience URL — /auren/admin.php — that sends people to the right
 * place:
 *   - a logged-in admin  -> the admin overview dashboard
 *   - anyone else logged in -> their own dashboard (via redirect_by_role)
 *   - a guest            -> the login page
 *
 * This exists because the user wanted a memorable admin URL rather than
 * having to type the full /auren/admin/dashboard.php path.
 */
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: /auren/auth/login.php');
    exit;
}

if (currentRole() === 'admin') {
    header('Location: /auren/admin/dashboard.php');
} else {
    // Not an admin — send them to wherever they do belong.
    header('Location: /auren/includes/redirect_by_role.php');
}
exit;
