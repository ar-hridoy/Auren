<?php
/**
 * includes/redirect_by_role.php
 *
 * Central place that decides "where does this logged-in user belong".
 * Used right after login, and as the fallback when requireRole() catches
 * someone on the wrong page. Keeping this logic in one file means adding
 * a new role later (or changing a dashboard path) is a one-line change,
 * not a find-and-replace across every page.
 */

require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    header('Location: /auren/auth/login.php');
    exit;
}

switch (currentRole()) {
    case 'employer':
        header('Location: /auren/employer/dashboard.php');
        break;
    case 'seeker':
        header('Location: /auren/seeker/dashboard.php');
        break;
    case 'admin':
        header('Location: /auren/admin/dashboard.php');
        break;
    default:
        // Unknown role — fail safe by logging out rather than looping.
        logoutUser();
        header('Location: /auren/auth/login.php');
}
exit;
