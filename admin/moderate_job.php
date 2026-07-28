<?php
/**
 * admin/moderate_job.php
 *
 * POST-only. Admin actions on a job: feature, unfeature, or remove
 * (soft delete). Removal sets deleted_at so the listing disappears from
 * every public query (which all filter deleted_at IS NULL) while the row
 * and its applications survive for the record.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auren/admin/jobs.php');
    exit;
}

$jobId = (int) ($_POST['job_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!in_array($action, ['feature', 'unfeature', 'remove'], true)) {
    setFlash('danger', 'Invalid action.');
    header('Location: /auren/admin/jobs.php');
    exit;
}

// Confirm the job exists and isn't already removed.
$check = $pdo->prepare('SELECT 1 FROM Jobs WHERE job_id = ? AND deleted_at IS NULL');
$check->execute([$jobId]);
if (!$check->fetchColumn()) {
    setFlash('danger', 'That job could not be found.');
    header('Location: /auren/admin/jobs.php');
    exit;
}

switch ($action) {
    case 'feature':
        $pdo->prepare('UPDATE Jobs SET is_featured = TRUE WHERE job_id = ?')->execute([$jobId]);
        setFlash('success', 'Job featured.');
        break;
    case 'unfeature':
        $pdo->prepare('UPDATE Jobs SET is_featured = FALSE WHERE job_id = ?')->execute([$jobId]);
        setFlash('success', 'Job is no longer featured.');
        break;
    case 'remove':
        $pdo->prepare('UPDATE Jobs SET deleted_at = NOW() WHERE job_id = ?')->execute([$jobId]);
        setFlash('success', 'Job listing removed from the marketplace.');
        break;
}

header('Location: /auren/admin/jobs.php');
exit;
