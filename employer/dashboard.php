<?php
/**
 * employer/dashboard.php
 *
 * Real dashboard: four stat cards from vw_employer_dashboard_stats
 * (see database/03_views.sql), plus a table of this employer's active
 * jobs with live applicant counts.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('employer');

$employerId = currentUserId();

// ---- Stat cards (from the view) ----
$statsStmt = $pdo->prepare('SELECT * FROM vw_employer_dashboard_stats WHERE employer_id = ?');
$statsStmt->execute([$employerId]);
$stats = $statsStmt->fetch() ?: ['total_jobs' => 0, 'active_jobs' => 0, 'closed_or_completed' => 0, 'total_applicants' => 0];

// ---- Active jobs table (top 5 most recent open jobs, with live applicant counts) ----
$activeJobsStmt = $pdo->prepare(
    'SELECT j.job_id, j.title, j.pay_rate, st.type_name AS salary_type,
            a.area_name, js.status_name,
            (SELECT COUNT(*) FROM Applications ap WHERE ap.job_id = j.job_id) AS applicant_count
     FROM Jobs j
     JOIN Salary_Types st ON j.salary_type_id = st.salary_type_id
     JOIN Areas a ON j.area_id = a.area_id
     JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE j.employer_id = ? AND js.status_name = "open"
     ORDER BY j.created_at DESC
     LIMIT 5'
);
$activeJobsStmt->execute([$employerId]);
$activeJobs = $activeJobsStmt->fetchAll();

$pageTitle = 'Employer Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/flash.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'dashboard'; require_once __DIR__ . '/../includes/sidebar_employer.php'; ?>
    <div class="auren-dashboard-content">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Welcome back, <?= htmlspecialchars(currentUserName()) ?></h1>
                <p class="text-muted mb-0">Here's what's happening with your job postings.</p>
            </div>
            <a href="/auren/employer/post_job.php" class="btn auren-btn-primary">
                <i class="bi bi-plus-lg"></i> Post a job
            </a>
        </div>

        <!-- Stat cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-briefcase"></i></div>
                    <div class="stat-number"><?= (int) $stats['total_jobs'] ?></div>
                    <div class="stat-label">Total jobs posted</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-lightning-charge"></i></div>
                    <div class="stat-number"><?= (int) $stats['active_jobs'] ?></div>
                    <div class="stat-label">Active jobs</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?= (int) $stats['closed_or_completed'] ?></div>
                    <div class="stat-label">Closed / completed</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-people"></i></div>
                    <div class="stat-number"><?= (int) $stats['total_applicants'] ?></div>
                    <div class="stat-label">Total applicants</div>
                </div>
            </div>
        </div>

        <!-- Active jobs table -->
        <div class="auren-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">Your active jobs</h2>
                <a href="/auren/employer/my_jobs.php" class="small">View all <i class="bi bi-arrow-right"></i></a>
            </div>

            <?php if (empty($activeJobs)): ?>
                <p class="text-muted mb-0">
                    You don't have any open jobs right now.
                    <a href="/auren/employer/post_job.php">Post your first job</a> to get started.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Job</th>
                                <th>Applicants</th>
                                <th>Status</th>
                                <th>Pay</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeJobs as $job): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($job['title']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($job['area_name']) ?></div>
                                    </td>
                                    <td><?= (int) $job['applicant_count'] ?></td>
                                    <td><span class="auren-badge auren-badge-open">Open</span></td>
                                    <td>Tk <?= number_format((float) $job['pay_rate']) ?>/<?= htmlspecialchars($job['salary_type']) ?></td>
                                    <td>
                                        <a href="/auren/employer/edit_job.php?id=<?= (int) $job['job_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
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
