<?php
/**
 * employer/update_application.php
 *
 * POST-only. Moves one application to accepted / rejected / pending.
 *
 * The critical guard: an employer may only change an application that
 * belongs to one of THEIR OWN jobs. We enforce that by joining Applications
 * -> Jobs and requiring Jobs.employer_id = the logged-in employer inside the
 * same UPDATE's WHERE clause. So even a forged application_id simply matches
 * zero rows rather than letting one employer touch another's applications.
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
$applicationId = (int) ($_POST['application_id'] ?? 0);
$jobId = (int) ($_POST['job_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

$allowed = ['accepted', 'rejected', 'pending'];
if (!in_array($decision, $allowed, true)) {
    setFlash('danger', 'Invalid action.');
    header('Location: /auren/employer/applicants.php?job_id=' . $jobId);
    exit;
}

$statusId = $pdo->prepare('SELECT status_id FROM Application_Statuses WHERE status_name = ?');
$statusId->execute([$decision]);
$statusId = $statusId->fetchColumn();

// Ownership-scoped update: the application's job must belong to this employer.
$update = $pdo->prepare(
    'UPDATE Applications a
     JOIN Jobs j ON a.job_id = j.job_id
     SET a.status_id = ?
     WHERE a.application_id = ? AND j.employer_id = ?'
);
$update->execute([$statusId, $applicationId, $employerId]);

if ($update->rowCount() > 0) {
    $msg = [
        'accepted' => 'Applicant accepted.',
        'rejected' => 'Applicant rejected.',
        'pending'  => 'Decision undone — applicant set back to pending.',
    ][$decision];
    setFlash('success', $msg);
} else {
    // Either the id was bogus or the job isn't theirs; same neutral message.
    setFlash('danger', 'Could not update that application.');
}

header('Location: /auren/employer/applicants.php?job_id=' . $jobId);
exit;
