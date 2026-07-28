<?php
/**
 * seeker/my_applications.php
 *
 * Lists every application the logged-in seeker has submitted, newest first,
 * with the live status of each (Pending / Accepted / Rejected) and a link
 * back to the job.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('seeker');

$seekerId = currentUserId();

$stmt = $pdo->prepare(
    'SELECT a.application_id, a.applied_at, a.cover_message,
            ast.status_name,
            j.job_id, j.title, j.pay_rate, j.deleted_at,
            st.type_name AS salary_type,
            ar.area_name,
            COALESCE(co.company_name, u.full_name) AS poster_name
     FROM Applications a
     JOIN Application_Statuses ast ON a.status_id = ast.status_id
     JOIN Jobs j ON a.job_id = j.job_id
     JOIN Salary_Types st ON j.salary_type_id = st.salary_type_id
     JOIN Areas ar ON j.area_id = ar.area_id
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies co ON j.company_id = co.company_id
     WHERE a.seeker_id = ?
     ORDER BY a.applied_at DESC'
);
$stmt->execute([$seekerId]);
$applications = $stmt->fetchAll();

$statusBadge = [
    'pending' => 'auren-badge-pending',
    'accepted' => 'auren-badge-accepted',
    'rejected' => 'auren-badge-rejected',
];

$pageTitle = 'My Applications';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'my_applications'; require_once __DIR__ . '/../includes/sidebar_seeker.php'; ?>
    <div class="auren-dashboard-content">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">My Applications</h1>
            <p class="text-muted mb-0"><?= count($applications) ?> application<?= count($applications) === 1 ? '' : 's' ?> submitted.</p>
        </div>

        <div class="auren-card">
            <?php if (empty($applications)): ?>
                <p class="text-muted mb-0">
                    You haven't applied to any jobs yet. <a href="/auren/browse_jobs.php">Browse jobs</a> to get started.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Job</th>
                                <th>Employer</th>
                                <th>Pay</th>
                                <th>Applied</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($app['title']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($app['poster_name']) ?></td>
                                    <td>Tk <?= number_format((float) $app['pay_rate']) ?>/<?= htmlspecialchars($app['salary_type']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars(date('M j, Y', strtotime($app['applied_at']))) ?></td>
                                    <td>
                                        <span class="auren-badge <?= $statusBadge[$app['status_name']] ?? '' ?>">
                                            <?= htmlspecialchars(ucfirst($app['status_name'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($app['deleted_at'] === null): ?>
                                            <a href="/auren/job_details.php?id=<?= (int) $app['job_id'] ?>" class="btn btn-sm btn-outline-secondary">View job</a>
                                        <?php else: ?>
                                            <span class="text-muted small">Job removed</span>
                                        <?php endif; ?>
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
