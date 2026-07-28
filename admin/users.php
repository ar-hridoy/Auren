<?php
/**
 * admin/users.php
 *
 * Admin user management: a filterable list of every account, with the
 * ability to verify / unverify each one. Verification is the admin's main
 * trust lever (Users.is_verified) — a verified badge is what employers and
 * seekers rely on when deciding whether to trust a counterparty.
 *
 * An admin can filter by role and by verification state. Admins are shown
 * but their own verify controls are hidden so an admin can't accidentally
 * lock themselves or another admin out of trusted status.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('admin');

// --- Filters ---
$roleFilter = $_GET['role'] ?? '';
$verifiedFilter = $_GET['verified'] ?? '';

$where = ['u.deleted_at IS NULL'];
$params = [];

if (in_array($roleFilter, ['employer', 'seeker', 'admin'], true)) {
    $where[] = 'r.role_name = ?';
    $params[] = $roleFilter;
}
if ($verifiedFilter === 'yes') {
    $where[] = 'u.is_verified = TRUE';
} elseif ($verifiedFilter === 'no') {
    $where[] = 'u.is_verified = FALSE';
}

$sql =
    "SELECT u.user_id, u.full_name, u.email, u.phone, u.is_verified, u.id_document_type, u.id_document_path, u.created_at, r.role_name
     FROM Users u JOIN Roles r ON u.role_id = r.role_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'users'; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>
    <div class="auren-dashboard-content">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Users</h1>
            <p class="text-muted mb-0"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?> shown.</p>
        </div>

        <!-- Filters -->
        <form method="GET" action="/auren/admin/users.php" class="auren-card mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-lg-3">
                    <label class="form-label small fw-semibold mb-1">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All roles</option>
                        <option value="employer" <?= $roleFilter === 'employer' ? 'selected' : '' ?>>Employers</option>
                        <option value="seeker" <?= $roleFilter === 'seeker' ? 'selected' : '' ?>>Job seekers</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admins</option>
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small fw-semibold mb-1">Verification</label>
                    <select name="verified" class="form-select">
                        <option value="">Any</option>
                        <option value="yes" <?= $verifiedFilter === 'yes' ? 'selected' : '' ?>>Verified</option>
                        <option value="no" <?= $verifiedFilter === 'no' ? 'selected' : '' ?>>Not verified</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <button type="submit" class="btn auren-btn-primary w-100">Filter</button>
                </div>
                <div class="col-6 col-lg-2">
                    <a href="/auren/admin/users.php" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
            </div>
        </form>

        <div class="auren-card">
            <?php if (empty($users)): ?>
                <p class="text-muted mb-0">No users match these filters.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted small">
                                <th>Name</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></td>
                                    <td><span class="auren-badge auren-badge-open"><?= htmlspecialchars(ucfirst($u['role_name'])) ?></span></td>
                                    <td class="small">
                                        <div><?= htmlspecialchars($u['email']) ?></div>
                                        <?php if (!empty($u['phone'])): ?>
                                            <div class="text-muted"><?= htmlspecialchars($u['phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars(date('M j, Y', strtotime($u['created_at']))) ?></td>
                                    <td>
                                        <?php if ($u['is_verified']): ?>
                                            <span class="text-success small"><i class="bi bi-patch-check-fill"></i> Verified</span>
                                        <?php elseif (!empty($u['id_document_path'])): ?>
                                            <span class="text-warning small"><i class="bi bi-hourglass-split"></i> ID pending</span>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-patch-exclamation"></i> No ID</span>
                                        <?php endif; ?>
                                        <?php if (!empty($u['id_document_path'])): ?>
                                            <div class="mt-1">
                                                <a href="/auren/view_id.php?user=<?= (int) $u['user_id'] ?>" target="_blank" class="small text-decoration-none">
                                                    <i class="bi bi-eye"></i> View <?= htmlspecialchars(strtoupper($u['id_document_type'] ?? 'ID')) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($u['role_name'] === 'admin'): ?>
                                            <span class="text-muted small">—</span>
                                        <?php else: ?>
                                            <form method="POST" action="/auren/admin/toggle_verify.php" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                                                <input type="hidden" name="verify" value="<?= $u['is_verified'] ? '0' : '1' ?>">
                                                <button type="submit" class="btn btn-sm <?= $u['is_verified'] ? 'btn-outline-secondary' : 'auren-btn-primary' ?>">
                                                    <?= $u['is_verified'] ? 'Unverify' : 'Verify' ?>
                                                </button>
                                            </form>
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
