<?php
/**
 * seeker/saved_jobs.php
 *
 * Lists the jobs the seeker has bookmarked. Each row links to the job and
 * offers a quick unsave. Jobs that have since been closed or deleted are
 * still shown but clearly marked, so the seeker understands why they can no
 * longer apply, rather than the row silently vanishing.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('seeker');

$seekerId = currentUserId();

$stmt = $pdo->prepare(
    'SELECT sj.saved_at,
            j.job_id, j.title, j.pay_rate, j.deleted_at,
            st.type_name AS salary_type,
            js.status_name,
            ar.area_name,
            COALESCE(co.company_name, u.full_name) AS poster_name
     FROM Saved_Jobs sj
     JOIN Jobs j ON sj.job_id = j.job_id
     JOIN Job_Statuses js ON j.status_id = js.status_id
     JOIN Salary_Types st ON j.salary_type_id = st.salary_type_id
     JOIN Areas ar ON j.area_id = ar.area_id
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies co ON j.company_id = co.company_id
     WHERE sj.seeker_id = ?
     ORDER BY sj.saved_at DESC'
);
$stmt->execute([$seekerId]);
$saved = $stmt->fetchAll();

$pageTitle = 'Saved Jobs';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'saved_jobs'; require_once __DIR__ . '/../includes/sidebar_seeker.php'; ?>
    <div class="auren-dashboard-content">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Saved Jobs</h1>
            <p class="text-muted mb-0"><?= count($saved) ?> job<?= count($saved) === 1 ? '' : 's' ?> saved.</p>
        </div>

        <?php if (empty($saved)): ?>
            <div class="auren-card">
                <p class="text-muted mb-0">
                    You haven't saved any jobs yet. <a href="/auren/browse_jobs.php">Browse jobs</a> and tap the bookmark to save them for later.
                </p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($saved as $job): ?>
                    <?php $available = $job['deleted_at'] === null && $job['status_name'] === 'open'; ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="auren-card h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <?php if ($available): ?>
                                    <span class="auren-badge auren-badge-open">Open</span>
                                <?php else: ?>
                                    <span class="auren-badge auren-badge-closed">No longer available</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="h6 fw-bold mb-1"><?= htmlspecialchars($job['title']) ?></h2>
                            <div class="text-muted small mb-2"><?= htmlspecialchars($job['poster_name']) ?></div>
                            <div class="small mb-3">
                                <div><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($job['area_name']) ?></div>
                                <div><i class="bi bi-cash text-muted"></i> Tk <?= number_format((float) $job['pay_rate']) ?>/<?= htmlspecialchars($job['salary_type']) ?></div>
                            </div>
                            <div class="mt-auto d-flex gap-2">
                                <?php if ($available): ?>
                                    <a href="/auren/job_details.php?id=<?= (int) $job['job_id'] ?>" class="btn btn-sm auren-btn-primary flex-grow-1">View & apply</a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary flex-grow-1" disabled>Unavailable</button>
                                <?php endif; ?>
                                <form method="POST" action="/auren/seeker/toggle_save.php">
                                    <input type="hidden" name="job_id" value="<?= (int) $job['job_id'] ?>">
                                    <input type="hidden" name="redirect" value="/auren/seeker/saved_jobs.php">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                        <i class="bi bi-bookmark-x"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
