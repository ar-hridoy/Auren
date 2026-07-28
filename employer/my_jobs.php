<?php
/**
 * employer/my_jobs.php
 *
 * Full list of every job this employer has ever posted (any status),
 * each row showing live applicant count, with Edit/Delete actions.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('employer');

$employerId = currentUserId();

$jobsStmt = $pdo->prepare(
    'SELECT j.job_id, j.title, j.pay_rate, st.type_name AS salary_type,
            a.area_name, js.status_name, j.created_at,
            (SELECT COUNT(*) FROM Applications ap WHERE ap.job_id = j.job_id) AS applicant_count
     FROM Jobs j
     JOIN Salary_Types st ON j.salary_type_id = st.salary_type_id
     JOIN Areas a ON j.area_id = a.area_id
     JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE j.employer_id = ?
     ORDER BY j.created_at DESC'
);
$jobsStmt->execute([$employerId]);
$jobs = $jobsStmt->fetchAll();

$badgeClass = [
    'open' => 'auren-badge-open',
    'closed' => 'auren-badge-closed',
    'filled' => 'auren-badge-filled',
    'expired' => 'auren-badge-expired',
];

$pageTitle = 'My Jobs';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'my_jobs'; require_once __DIR__ . '/../includes/sidebar_employer.php'; ?>
    <div class="auren-dashboard-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">My Jobs</h1>
                <p class="text-muted mb-0"><?= count($jobs) ?> job<?= count($jobs) === 1 ? '' : 's' ?> posted in total.</p>
            </div>
            <a href="/auren/employer/post_job.php" class="btn auren-btn-primary">
                <i class="bi bi-plus-lg"></i> Post a job
            </a>
        </div>

        <div class="auren-card">
            <?php if (empty($jobs)): ?>
                <p class="text-muted mb-0">
                    You haven't posted any jobs yet. <a href="/auren/employer/post_job.php">Post your first job</a>.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Job</th>
                                <th>Location</th>
                                <th>Pay</th>
                                <th>Applicants</th>
                                <th>Status</th>
                                <th>Posted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($job['title']) ?></td>
                                    <td><?= htmlspecialchars($job['area_name']) ?></td>
                                    <td>Tk <?= number_format((float) $job['pay_rate']) ?>/<?= htmlspecialchars($job['salary_type']) ?></td>
                                    <td>
                                        <a href="/auren/employer/applicants.php?job_id=<?= (int) $job['job_id'] ?>">
                                            <?= (int) $job['applicant_count'] ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="auren-badge <?= $badgeClass[$job['status_name']] ?? '' ?>">
                                            <?= htmlspecialchars(ucfirst($job['status_name'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars(date('M j, Y', strtotime($job['created_at']))) ?></td>
                                    <td class="text-nowrap">
                                        <a href="/auren/employer/edit_job.php?id=<?= (int) $job['job_id'] ?>"
                                           class="btn btn-sm btn-outline-secondary">Edit</a>
                                        <form method="POST" action="/auren/employer/delete_job.php" class="d-inline"
                                              onsubmit="return confirm('Delete this job? This also removes its applications and cannot be undone.');">
                                            <input type="hidden" name="job_id" value="<?= (int) $job['job_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
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
