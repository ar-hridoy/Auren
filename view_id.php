<?php
/**
 * view_id.php?user=ID
 *
 * Streams a user's uploaded identity document (NID/passport). Because these
 * are sensitive documents, access is restricted: a user may view only their
 * OWN document, and admins may view anyone's (for verification review). The
 * files live in uploads/ids/, which is blocked from direct web access.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/uploads.php';
requireLogin();

$targetId = isset($_GET['user']) && ctype_digit((string) $_GET['user']) ? (int) $_GET['user'] : 0;
$viewerId = currentUserId();
$isAdmin = currentRole() === 'admin';

// Authorization: owner or admin only.
if ($targetId === 0 || (!$isAdmin && $targetId !== $viewerId)) {
    http_response_code(403);
    exit('Not authorized to view this document.');
}

$stmt = $pdo->prepare('SELECT id_document_path FROM Users WHERE user_id = ?');
$stmt->execute([$targetId]);
$path = $stmt->fetchColumn();

if (!$path) {
    http_response_code(404);
    exit('No identity document on file.');
}

$full = ID_DIR . '/' . basename($path); // basename() guards against path traversal
if (!is_file($full)) {
    http_response_code(404);
    exit('Document file not found.');
}

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
$mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($full));
header('Content-Disposition: inline; filename="id-document.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($full);
exit;
