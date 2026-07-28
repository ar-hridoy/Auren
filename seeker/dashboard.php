<?php
/**
 * seeker/dashboard.php
 *
 * The job seeker's home base: quick stats (applications by status, saved
 * jobs, resume readiness) plus their most recent applications. Everything
 * here is scoped strictly to the logged-in seeker — a seeker never sees
 * another seeker's data. Stats are computed with conditional aggregation
 * in a single pass over Applications rather than several COUNT queries.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('seeker');

$seekerId = currentUserId();

// Application stats in one query.
$statStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN ast.status_name = 'pending'  THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN ast.status_name = 'accepted' THEN 1 ELSE 0 END) AS accepted,
        SUM(CASE WHEN ast.status_name = 'rejected' THEN 1 ELSE 0 END) AS rejected
     FROM Applications a
     JOIN Application_Statuses ast ON a.status_id = ast.status_id
     WHERE a.seeker_id = ?"
);
$statStmt->execute([$seekerId]);
$stats = $statStmt->fetch() ?: ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];

$savedStmt = $pdo->prepare('SELECT COUNT(*) FROM Saved_Jobs WHERE seeker_id = ?');
$savedStmt->execute([$seekerId]);
$savedCount = (int) $savedStmt->fetchColumn();

$resumeStmt = $pdo->prepare('SELECT resume_id, headline FROM Resumes WHERE seeker_id = ?');
$resumeStmt->execute([$seekerId]);
$resume = $resumeStmt->fetch();

// Recent applications (latest 5).
$recentStmt = $pdo->prepare(
    'SELECT a.applied_at, ast.status_name, j.job_id, j.title,
            COALESCE(co.company_name, u.full_name) AS poster_name
     FROM Applications a
     JOIN Application_Statuses ast ON a.status_id = ast.status_id
     JOIN Jobs j ON a.job_id = j.job_id
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies co ON j.company_id = co.company_id
     WHERE a.seeker_id = ?
     ORDER BY a.applied_at DESC
     LIMIT 5'
);
$recentStmt->execute([$seekerId]);
$recent = $recentStmt->fetchAll();

$statusBadge = [
    'pending' => 'auren-badge-pending',
    'accepted' => 'auren-badge-accepted',
    'rejected' => 'auren-badge-rejected',
];

$pageTitle = 'Job Seeker Dashboard';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'dashboard'; require_once __DIR__ . '/../includes/sidebar_seeker.php'; ?>
    <div class="auren-dashboard-content">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Welcome back, <?= htmlspecialchars(currentUserName()) ?></h1>
                <p class="text-muted mb-0">Here's a snapshot of your job search.</p>
            </div>
            <a href="/auren/browse_jobs.php" class="btn auren-btn-primary"><i class="bi bi-search"></i> Browse jobs</a>
        </div>

        <?php if (!$resume): ?>
            <div class="alert alert-warning">
                You don't have a resume yet — you'll need one to apply for jobs.
                <a href="/auren/seeker/resume.php" class="alert-link">Create your resume</a>.
            </div>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-send-check"></i></div>
                    <div class="stat-number"><?= (int) $stats['total'] ?></div>
                    <div class="stat-label">Total applications</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?= (int) $stats['pending'] ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?= (int) $stats['accepted'] ?></div>
                    <div class="stat-label">Accepted</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-bookmark-heart"></i></div>
                    <div class="stat-number"><?= $savedCount ?></div>
                    <div class="stat-label">Saved jobs</div>
                </div>
            </div>
        </div>

        <!-- Recent applications -->
        <div class="auren-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">Recent applications</h2>
                <a href="/auren/seeker/my_applications.php" class="small">View all <i class="bi bi-arrow-right"></i></a>
            </div>

            <?php if (empty($recent)): ?>
                <p class="text-muted mb-0">
                    You haven't applied to any jobs yet. <a href="/auren/browse_jobs.php">Find your first job</a>.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Job</th>
                                <th>Employer</th>
                                <th>Applied</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $app): ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="/auren/job_details.php?id=<?= (int) $app['job_id'] ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($app['title']) ?>
                                        </a>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($app['poster_name']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars(date('M j, Y', strtotime($app['applied_at']))) ?></td>
                                    <td>
                                        <span class="auren-badge <?= $statusBadge[$app['status_name']] ?? '' ?>">
                                            <?= htmlspecialchars(ucfirst($app['status_name'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
