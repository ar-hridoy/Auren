<?php
/**
 * admin/toggle_verify.php
 *
 * POST-only. Sets a user's is_verified flag on or off. Admin-only, and
 * refuses to touch admin accounts (defence in depth: the UI already hides
 * the control for admins, but the handler enforces it too so a forged POST
 * can't flip an admin's status).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auren/admin/users.php');
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$verify = ($_POST['verify'] ?? '') === '1';

// Never allow changing an admin's verification via this endpoint.
$roleStmt = $pdo->prepare(
    'SELECT r.role_name FROM Users u JOIN Roles r ON u.role_id = r.role_id WHERE u.user_id = ?'
);
$roleStmt->execute([$userId]);
$targetRole = $roleStmt->fetchColumn();

if (!$targetRole) {
    setFlash('danger', 'That user could not be found.');
    header('Location: /auren/admin/users.php');
    exit;
}
if ($targetRole === 'admin') {
    setFlash('danger', 'Admin accounts cannot be modified here.');
    header('Location: /auren/admin/users.php');
    exit;
}

$upd = $pdo->prepare('UPDATE Users SET is_verified = ? WHERE user_id = ? AND deleted_at IS NULL');
$upd->execute([$verify ? 1 : 0, $userId]);

setFlash('success', $verify ? 'User verified.' : 'User verification removed.');
header('Location: /auren/admin/users.php');
exit;
