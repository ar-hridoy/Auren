<?php
/**
 * employer/applicants.php
 *
 * Shows every applicant for one job — but only if that job belongs to the
 * logged-in employer. The ownership check is the whole security story here:
 * without it, an employer could read applicants (and their resumes/contact
 * details) for a competitor's job just by changing job_id in the URL.
 *
 * Each applicant row exposes Accept / Reject actions handled by
 * update_application.php.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('employer');

$employerId = currentUserId();
$jobId = (int) ($_GET['job_id'] ?? 0);

// Ownership check: this job must belong to this employer.
$jobStmt = $pdo->prepare('SELECT job_id, title FROM Jobs WHERE job_id = ? AND employer_id = ? AND deleted_at IS NULL');
$jobStmt->execute([$jobId, $employerId]);
$job = $jobStmt->fetch();

if (!$job) {
    setFlash('danger', 'Job not found, or you do not have permission to view its applicants.');
    header('Location: /auren/employer/my_jobs.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT a.application_id, a.applied_at, a.cover_message,
            ast.status_name,
            u.full_name, u.email, u.phone,
            r.resume_id, r.headline, r.summary, r.education, r.experience, r.resume_path
     FROM Applications a
     JOIN Application_Statuses ast ON a.status_id = ast.status_id
     JOIN Seekers s ON a.seeker_id = s.user_id
     JOIN Users u ON s.user_id = u.user_id
     JOIN Resumes r ON a.resume_id = r.resume_id
     WHERE a.job_id = ?
     ORDER BY a.applied_at DESC'
);
$stmt->execute([$jobId]);
$applicants = $stmt->fetchAll();

// Skills per resume (one query, grouped in PHP) so each applicant card can
// show the seeker's skill tags without an N+1 query loop.
$skillsByResume = [];
if (!empty($applicants)) {
    $resumeIds = array_column($applicants, 'resume_id');
    $placeholders = implode(',', array_fill(0, count($resumeIds), '?'));
    $skillStmt = $pdo->prepare(
        "SELECT rs.resume_id, sk.skill_name
         FROM Resume_Skills rs JOIN Skills sk ON rs.skill_id = sk.skill_id
         WHERE rs.resume_id IN ($placeholders)
         ORDER BY sk.skill_name"
    );
    $skillStmt->execute($resumeIds);
    foreach ($skillStmt->fetchAll() as $row) {
        $skillsByResume[$row['resume_id']][] = $row['skill_name'];
    }
}

$statusBadge = [
    'pending' => 'auren-badge-pending',
    'accepted' => 'auren-badge-accepted',
    'rejected' => 'auren-badge-rejected',
];

$pageTitle = 'Applicants';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'my_jobs'; require_once __DIR__ . '/../includes/sidebar_employer.php'; ?>
    <div class="auren-dashboard-content">
        <a href="/auren/employer/my_jobs.php" class="small text-muted d-inline-block mb-3"><i class="bi bi-arrow-left"></i> Back to my jobs</a>
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Applicants</h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($job['title']) ?> — <?= count($applicants) ?> applicant<?= count($applicants) === 1 ? '' : 's' ?></p>
        </div>

        <?php if (empty($applicants)): ?>
            <div class="auren-card">
                <p class="text-muted mb-0">No one has applied to this job yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($applicants as $app): ?>
                <div class="auren-card mb-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <div>
                            <h2 class="h5 fw-bold mb-0"><?= htmlspecialchars($app['full_name']) ?></h2>
                            <?php if (!empty($app['headline'])): ?>
                                <div class="text-muted"><?= htmlspecialchars($app['headline']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="auren-badge <?= $statusBadge[$app['status_name']] ?? '' ?>">
                            <?= htmlspecialchars(ucfirst($app['status_name'])) ?>
                        </span>
                    </div>

                    <div class="small text-muted mb-2">
                        <i class="bi bi-envelope"></i> <?= htmlspecialchars($app['email']) ?>
                        <?php if (!empty($app['phone'])): ?>
                            &nbsp; <i class="bi bi-telephone"></i> <?= htmlspecialchars($app['phone']) ?>
                        <?php endif; ?>
                        &nbsp; <i class="bi bi-clock"></i> Applied <?= htmlspecialchars(date('M j, Y', strtotime($app['applied_at']))) ?>
                    </div>

                    <?php if (!empty($skillsByResume[$app['resume_id']])): ?>
                        <div class="mb-2">
                            <?php foreach ($skillsByResume[$app['resume_id']] as $skill): ?>
                                <span class="auren-badge auren-badge-open me-1"><?= htmlspecialchars($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($app['cover_message'])): ?>
                        <div class="mb-2">
                            <div class="small fw-semibold text-muted">Cover message</div>
                            <p class="mb-0" style="white-space: pre-line;"><?= htmlspecialchars($app['cover_message']) ?></p>
                        </div>
                    <?php endif; ?>

                    <details class="mb-3">
                        <summary class="small fw-semibold" style="cursor: pointer;">View full resume</summary>
                        <div class="mt-2 small">
                            <?php if (!empty($app['summary'])): ?>
                                <p class="mb-1"><strong>Summary:</strong> <?= htmlspecialchars($app['summary']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($app['education'])): ?>
                                <p class="mb-1"><strong>Education:</strong> <?= htmlspecialchars($app['education']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($app['experience'])): ?>
                                <p class="mb-1"><strong>Experience:</strong> <?= htmlspecialchars($app['experience']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($app['resume_path'])): ?>
                                <p class="mb-0"><a href="<?= htmlspecialchars($app['resume_path']) ?>" target="_blank" rel="noopener">Download attached file</a></p>
                            <?php endif; ?>
                        </div>
                    </details>

                    <?php if ($app['status_name'] === 'pending'): ?>
                        <div class="d-flex gap-2">
                            <form method="POST" action="/auren/employer/update_application.php">
                                <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                <input type="hidden" name="job_id" value="<?= (int) $jobId ?>">
                                <input type="hidden" name="decision" value="accepted">
                                <button type="submit" class="btn btn-sm auren-btn-primary"><i class="bi bi-check-lg"></i> Accept</button>
                            </form>
                            <form method="POST" action="/auren/employer/update_application.php">
                                <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                <input type="hidden" name="job_id" value="<?= (int) $jobId ?>">
                                <input type="hidden" name="decision" value="rejected">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted mb-0">
                            You <?= htmlspecialchars($app['status_name']) ?> this applicant.
                            <form method="POST" action="/auren/employer/update_application.php" class="d-inline">
                                <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                <input type="hidden" name="job_id" value="<?= (int) $jobId ?>">
                                <input type="hidden" name="decision" value="pending">
                                <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Undo</button>
                            </form>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
