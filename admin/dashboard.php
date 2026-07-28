<?php
/**
 * admin/dashboard.php
 *
 * Platform overview for the Admin role. Unlike the employer/seeker
 * dashboards (which are scoped to one user's own data), the admin sees
 * aggregate figures across the whole marketplace: how many users of each
 * role, jobs by status, total applications and companies, plus a queue of
 * the newest users and jobs so moderation can start from here.
 *
 * Everything is read-only on this page; the actual moderation actions live
 * on users.php and jobs.php.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('admin');

// --- User counts by role (single grouped query) ---
$roleCounts = ['employer' => 0, 'seeker' => 0, 'admin' => 0];
$rc = $pdo->query(
    "SELECT r.role_name, COUNT(*) AS c
     FROM Users u JOIN Roles r ON u.role_id = r.role_id
     WHERE u.deleted_at IS NULL
     GROUP BY r.role_name"
)->fetchAll();
foreach ($rc as $row) {
    $roleCounts[$row['role_name']] = (int) $row['c'];
}
$totalUsers = array_sum($roleCounts);

// --- Unverified users (a moderation signal) ---
$unverified = (int) $pdo->query(
    'SELECT COUNT(*) FROM Users WHERE is_verified = FALSE AND deleted_at IS NULL'
)->fetchColumn();

// --- Jobs by status ---
$jobStatus = ['open' => 0, 'closed' => 0, 'filled' => 0, 'expired' => 0];
$js = $pdo->query(
    "SELECT js.status_name, COUNT(*) AS c
     FROM Jobs j JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE j.deleted_at IS NULL
     GROUP BY js.status_name"
)->fetchAll();
foreach ($js as $row) {
    $jobStatus[$row['status_name']] = (int) $row['c'];
}
$totalJobs = array_sum($jobStatus);

// --- Other totals ---
$totalApplications = (int) $pdo->query('SELECT COUNT(*) FROM Applications')->fetchColumn();
$totalCompanies = (int) $pdo->query('SELECT COUNT(*) FROM Companies')->fetchColumn();

// --- Newest users (moderation queue) ---
$recentUsers = $pdo->query(
    "SELECT u.user_id, u.full_name, u.email, u.is_verified, u.created_at, r.role_name
     FROM Users u JOIN Roles r ON u.role_id = r.role_id
     WHERE u.deleted_at IS NULL
     ORDER BY u.created_at DESC
     LIMIT 5"
)->fetchAll();

// --- Newest jobs ---
$recentJobs = $pdo->query(
    "SELECT j.job_id, j.title, j.created_at, js.status_name,
            COALESCE(co.company_name, u.full_name) AS poster_name
     FROM Jobs j
     JOIN Job_Statuses js ON j.status_id = js.status_id
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies co ON j.company_id = co.company_id
     WHERE j.deleted_at IS NULL
     ORDER BY j.created_at DESC
     LIMIT 5"
)->fetchAll();

$pageTitle = 'Admin Overview';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'dashboard'; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>
    <div class="auren-dashboard-content">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Platform Overview</h1>
            <p class="text-muted mb-0">A snapshot of activity across Auren.</p>
        </div>

        <!-- Primary stat cards -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-people"></i></div>
                    <div class="stat-number"><?= $totalUsers ?></div>
                    <div class="stat-label">Total users</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-briefcase"></i></div>
                    <div class="stat-number"><?= $totalJobs ?></div>
                    <div class="stat-label">Total jobs</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-send-check"></i></div>
                    <div class="stat-number"><?= $totalApplications ?></div>
                    <div class="stat-label">Applications</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="auren-stat-card">
                    <div class="auren-stat-icon mb-2"><i class="bi bi-building"></i></div>
                    <div class="stat-number"><?= $totalCompanies ?></div>
                    <div class="stat-label">Companies</div>
                </div>
            </div>
        </div>

        <!-- Breakdown row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="auren-card h-100">
                    <h2 class="h6 fw-bold mb-3">Users by role</h2>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><i class="bi bi-person-badge text-muted"></i> Employers</span>
                        <span class="fw-semibold"><?= $roleCounts['employer'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><i class="bi bi-person text-muted"></i> Job seekers</span>
                        <span class="fw-semibold"><?= $roleCounts['seeker'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><i class="bi bi-shield-check text-muted"></i> Admins</span>
                        <span class="fw-semibold"><?= $roleCounts['admin'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span><i class="bi bi-patch-exclamation text-warning"></i> Awaiting verification</span>
                        <span class="fw-semibold"><?= $unverified ?></span>
                    </div>
                    <a href="/auren/admin/users.php" class="small d-inline-block mt-2">Manage users <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="auren-card h-100">
                    <h2 class="h6 fw-bold mb-3">Jobs by status</h2>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><span class="auren-badge auren-badge-open">Open</span></span>
                        <span class="fw-semibold"><?= $jobStatus['open'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><span class="auren-badge auren-badge-accepted">Filled</span></span>
                        <span class="fw-semibold"><?= $jobStatus['filled'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span><span class="auren-badge auren-badge-closed">Closed</span></span>
                        <span class="fw-semibold"><?= $jobStatus['closed'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span><span class="auren-badge auren-badge-rejected">Expired</span></span>
                        <span class="fw-semibold"><?= $jobStatus['expired'] ?></span>
                    </div>
                    <a href="/auren/admin/jobs.php" class="small d-inline-block mt-2">Moderate jobs <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="auren-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">Newest users</h2>
                        <a href="/auren/admin/users.php" class="small">View all</a>
                    </div>
                    <?php if (empty($recentUsers)): ?>
                        <p class="text-muted mb-0">No users yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <tbody>
                                    <?php foreach ($recentUsers as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
                                            </td>
                                            <td><span class="auren-badge auren-badge-open"><?= htmlspecialchars(ucfirst($u['role_name'])) ?></span></td>
                                            <td>
                                                <?php if ($u['is_verified']): ?>
                                                    <span class="text-success small"><i class="bi bi-patch-check-fill"></i> Verified</span>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="bi bi-patch-exclamation"></i> Pending</span>
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
            <div class="col-lg-6">
                <div class="auren-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">Newest jobs</h2>
                        <a href="/auren/admin/jobs.php" class="small">View all</a>
                    </div>
                    <?php if (empty($recentJobs)): ?>
                        <p class="text-muted mb-0">No jobs yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <tbody>
                                    <?php foreach ($recentJobs as $j): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($j['title']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($j['poster_name']) ?></div>
                                            </td>
                                            <td><span class="auren-badge auren-badge-open"><?= htmlspecialchars(ucfirst($j['status_name'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
