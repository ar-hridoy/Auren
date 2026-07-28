<?php
/**
 * seeker/apply.php
 *
 * POST-only. Creates one Application row for the logged-in seeker against a
 * job. Business Rule R10 ("a seeker may apply to a given job at most once")
 * is ultimately enforced by the database's UNIQUE(job_id, seeker_id)
 * constraint — this handler ALSO checks first, so the normal case shows a
 * friendly message instead of a raw duplicate-key error, but the DB remains
 * the source of truth if two requests race.
 *
 * The application must reference the seeker's resume (Applications.resume_id
 * is NOT NULL), so a missing resume short-circuits to the resume page.
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
$coverMessage = trim($_POST['cover_message'] ?? '');

// The job must exist and still be open.
$jobStmt = $pdo->prepare(
    "SELECT j.job_id FROM Jobs j
     JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE j.job_id = ? AND js.status_name = 'open' AND j.deleted_at IS NULL"
);
$jobStmt->execute([$jobId]);
if (!$jobStmt->fetch()) {
    setFlash('danger', 'That job is no longer accepting applications.');
    header('Location: /auren/browse_jobs.php');
    exit;
}

// The seeker must have a resume to attach.
$resumeStmt = $pdo->prepare('SELECT resume_id FROM Resumes WHERE seeker_id = ?');
$resumeStmt->execute([$seekerId]);
$resumeId = $resumeStmt->fetchColumn();
if (!$resumeId) {
    setFlash('warning', 'Please create a resume before applying.');
    header('Location: /auren/seeker/resume.php');
    exit;
}

// Friendly duplicate check (the UNIQUE constraint is the real guarantee).
$dupStmt = $pdo->prepare('SELECT 1 FROM Applications WHERE job_id = ? AND seeker_id = ?');
$dupStmt->execute([$jobId, $seekerId]);
if ($dupStmt->fetchColumn()) {
    setFlash('info', 'You have already applied to this job.');
    header('Location: /auren/job_details.php?id=' . $jobId);
    exit;
}

$pendingStatusId = $pdo->query("SELECT status_id FROM Application_Statuses WHERE status_name = 'pending'")->fetchColumn();

try {
    $insert = $pdo->prepare(
        'INSERT INTO Applications (job_id, seeker_id, resume_id, status_id, cover_message)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $jobId, $seekerId, $resumeId, $pendingStatusId,
        $coverMessage !== '' ? $coverMessage : null,
    ]);
    setFlash('success', 'Your application has been submitted.');
} catch (PDOException $e) {
    // 23000 = integrity constraint violation (the UNIQUE caught a race).
    if ($e->getCode() === '23000') {
        setFlash('info', 'You have already applied to this job.');
    } else {
        throw $e;
    }
}

header('Location: /auren/job_details.php?id=' . $jobId);
exit;
