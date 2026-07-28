<?php
/**
 * auth/logout.php
 *
 * Clears the logged-in user's session and sends them back to the
 * homepage with a confirmation message. See logoutUser() in auth.php
 * for why this uses session_regenerate_id() rather than session_destroy().
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';

logoutUser();
setFlash('info', 'You have been logged out.');

header('Location: /auren/index.php');
exit;
