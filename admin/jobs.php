<?php
/**
 * admin/jobs.php
 *
 * Admin job moderation: every active job listing with two levers —
 *   - Feature / unfeature  (Jobs.is_featured): promote a good listing
 *   - Remove               (soft delete via Jobs.deleted_at): take down a
 *                          spam / inappropriate listing without destroying
 *                          the row (applications referencing it stay intact)
 *
 * Removal is a soft delete on purpose: hard-deleting a job would orphan or
 * cascade its applications, losing the audit trail. Setting deleted_at hides
 * it everywhere public (every seeker-facing query already filters
 * deleted_at IS NULL) while keeping history.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('admin');

$statusFilter = $_GET['status'] ?? '';

$where = ['j.deleted_at IS NULL'];
$params = [];
if (in_array($statusFilter, ['open', 'closed', 'filled', 'expired'], true)) {
    $where[] = 'js.status_name = ?';
    $params[] = $statusFilter;
}

$sql =
    "SELECT j.job_id, j.title, j.is_featured, j.created_at, j.job_views,
            js.status_name,
            COALESCE(co.company_name, u.full_name) AS poster_name,
            (SELECT COUNT(*) FROM Applications a WHERE a.job_id = j.job_id) AS applicant_count
     FROM Jobs j
     JOIN Job_Statuses js ON j.status_id = js.status_id
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies co ON j.company_id = co.company_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY j.is_featured DESC, j.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$pageTitle = 'Job Moderation';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'jobs'; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>
    <div class="auren-dashboard-content">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Job Moderation</h1>
            <p class="text-muted mb-0"><?= count($jobs) ?> active job<?= count($jobs) === 1 ? '' : 's' ?>.</p>
        </div>

        <form method="GET" action="/auren/admin/jobs.php" class="auren-card mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-8 col-lg-3">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="filled" <?= $statusFilter === 'filled' ? 'selected' : '' ?>>Filled</option>
                        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-4 col-lg-2">
                    <button type="submit" class="btn auren-btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>

        <div class="auren-card">
            <?php if (empty($jobs)): ?>
                <p class="text-muted mb-0">No jobs match these filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Job</th>
                                <th>Status</th>
                                <th>Applicants</th>
                                <th>Views</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $j): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            <a href="/auren/job_details.php?id=<?= (int) $j['job_id'] ?>" class="text-decoration-none text-dark" target="_blank">
                                                <?= htmlspecialchars($j['title']) ?>
                                            </a>
                                            <?php if ($j['is_featured']): ?>
                                                <span class="auren-badge auren-badge-pending ms-1"><i class="bi bi-star-fill"></i> Featured</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small"><?= htmlspecialchars($j['poster_name']) ?></div>
                                    </td>
                                    <td><span class="auren-badge auren-badge-open"><?= htmlspecialchars(ucfirst($j['status_name'])) ?></span></td>
                                    <td><?= (int) $j['applicant_count'] ?></td>
                                    <td class="text-muted"><?= (int) $j['job_views'] ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="/auren/admin/moderate_job.php">
                                                <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                                <input type="hidden" name="action" value="<?= $j['is_featured'] ? 'unfeature' : 'feature' ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $j['is_featured'] ? 'Remove feature' : 'Feature this job' ?>">
                                                    <i class="bi <?= $j['is_featured'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="/auren/admin/moderate_job.php"
                                                onsubmit="return confirm('Remove this job listing? It will be hidden from the marketplace.');">
                                                <input type="hidden" name="job_id" value="<?= (int) $j['job_id'] ?>">
                                                <input type="hidden" name="action" value="remove">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove listing">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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
