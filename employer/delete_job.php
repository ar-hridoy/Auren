<?php
/**
 * employer/delete_job.php
 *
 * POST-only action (never a plain link — deleting via GET would let a
 * browser prefetch or a re-visited URL accidentally delete a job).
 * Ownership is re-checked here even though my_jobs.php only ever shows
 * delete buttons for the employer's own jobs — the check has to live on
 * the page that does the deleting, not just the page that links to it.
 *
 * Applications and Saved_Jobs rows referencing this job are removed
 * automatically by the schema's ON DELETE CASCADE — no manual cleanup
 * needed here.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('employer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auren/employer/my_jobs.php');
    exit;
}

$employerId = currentUserId();
$jobId = (int) ($_POST['job_id'] ?? 0);

$delete = $pdo->prepare('DELETE FROM Jobs WHERE job_id = ? AND employer_id = ?');
$delete->execute([$jobId, $employerId]);

if ($delete->rowCount() > 0) {
    setFlash('success', 'Job deleted.');
} else {
    setFlash('danger', 'Job not found, or you do not have permission to delete it.');
}

header('Location: /auren/employer/my_jobs.php');
exit;
