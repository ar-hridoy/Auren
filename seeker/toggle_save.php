<?php
/**
 * seeker/toggle_save.php
 *
 * POST-only. Toggles a Saved_Jobs row for (seeker, job): if it's already
 * saved, unsave it; otherwise save it. Redirects back to wherever the user
 * clicked from (browse or job details), which is passed in a hidden field.
 *
 * The redirect target is validated against a small allow-list of internal
 * paths so this can't be turned into an open-redirect via a crafted form.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('seeker');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auren/browse_jobs.php');
    exit;
}

$seekerId = currentUserId();
$jobId = (int) ($_POST['job_id'] ?? 0);

// Only allow redirects back into our own app.
$redirect = $_POST['redirect'] ?? '/auren/browse_jobs.php';
if (strpos($redirect, '/auren/') !== 0) {
    $redirect = '/auren/browse_jobs.php';
}

// Make sure the job exists before saving (avoids orphan-looking rows).
$jobStmt = $pdo->prepare('SELECT 1 FROM Jobs WHERE job_id = ? AND deleted_at IS NULL');
$jobStmt->execute([$jobId]);
if (!$jobStmt->fetchColumn()) {
    setFlash('danger', 'That job could not be found.');
    header('Location: ' . $redirect);
    exit;
}

$existsStmt = $pdo->prepare('SELECT 1 FROM Saved_Jobs WHERE seeker_id = ? AND job_id = ?');
$existsStmt->execute([$seekerId, $jobId]);

if ($existsStmt->fetchColumn()) {
    $pdo->prepare('DELETE FROM Saved_Jobs WHERE seeker_id = ? AND job_id = ?')->execute([$seekerId, $jobId]);
    setFlash('info', 'Removed from saved jobs.');
} else {
    $pdo->prepare('INSERT INTO Saved_Jobs (seeker_id, job_id) VALUES (?, ?)')->execute([$seekerId, $jobId]);
    setFlash('success', 'Job saved.');
}

header('Location: ' . $redirect);
exit;
