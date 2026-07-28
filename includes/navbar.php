<?php
/**
 * includes/navbar.php
 *
 * Included automatically by header.php on every page. Shows Sign in /
 * Get Started for guests; shows a professional profile avatar with a
 * role-aware dropdown menu (Profile, Dashboard, role shortcuts, Log out)
 * when someone is logged in.
 */
require_once __DIR__ . '/auth.php';
$loggedIn = isLoggedIn();
$role = currentRole();
$name = currentUserName() ?? '';

$dashboardUrl = '/auren/includes/redirect_by_role.php';
if ($role === 'employer') $dashboardUrl = '/auren/employer/dashboard.php';
if ($role === 'seeker')   $dashboardUrl = '/auren/seeker/dashboard.php';
if ($role === 'admin')    $dashboardUrl = '/auren/admin/dashboard.php';

// Initials for the avatar (up to two letters).
$initials = '';
if ($name !== '') {
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
    }
}
$roleLabel = ['employer' => 'Employer', 'seeker' => 'Job Seeker', 'admin' => 'Admin'][$role] ?? '';

// Profile photo for the avatar (falls back to initials if none).
$navPhoto = null;
if ($loggedIn) {
    require_once __DIR__ . '/../config/database.php';
    try {
        $ps = $pdo->prepare('SELECT profile_photo FROM Users WHERE user_id = ?');
        $ps->execute([currentUserId()]);
        $navPhoto = $ps->fetchColumn() ?: null;
    } catch (Throwable $e) {
        $navPhoto = null; // column may not exist until migration 06 is run
    }
}

// Role-specific quick links shown inside the dropdown.
$roleLinks = [];
if ($role === 'employer') {
    $roleLinks = [
        ['/auren/employer/post_job.php', 'bi-plus-square', 'Post a Job'],
        ['/auren/employer/my_jobs.php', 'bi-list-check', 'My Jobs'],
        ['/auren/employer/company_profile.php', 'bi-building', 'Company Profile'],
    ];
} elseif ($role === 'seeker') {
    $roleLinks = [
        ['/auren/seeker/my_applications.php', 'bi-send-check', 'My Applications'],
        ['/auren/seeker/saved_jobs.php', 'bi-bookmark-heart', 'Saved Jobs'],
        ['/auren/seeker/resume.php', 'bi-file-earmark-text', 'My Resume'],
    ];
} elseif ($role === 'admin') {
    $roleLinks = [
        ['/auren/admin/users.php', 'bi-people', 'Manage Users'],
        ['/auren/admin/jobs.php', 'bi-briefcase', 'Job Moderation'],
        ['/auren/admin/categories.php', 'bi-tags', 'Categories & Skills'],
    ];
}
?>
<nav class="navbar navbar-expand-lg auren-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand auren-brand" href="/auren/index.php">
            <img src="/auren/assets/img/auren-logo-nav.png" alt="Auren logo"> Auren
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#aurenNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="aurenNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 auren-nav-center">
                <li class="nav-item"><a class="nav-link" href="/auren/index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="/auren/browse_jobs.php">Browse Jobs</a></li>
                <li class="nav-item"><a class="nav-link" href="/auren/for_employers.php">For Employers</a></li>
                <li class="nav-item"><a class="nav-link" href="/auren/how_it_works.php">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="/auren/about.php">About</a></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($loggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link auren-avatar-toggle d-flex align-items-center gap-2" href="#" id="aurenProfileMenu"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($navPhoto): ?>
                                <img src="/auren/uploads/avatars/<?= htmlspecialchars(rawurlencode($navPhoto)) ?>" alt="<?= htmlspecialchars($name) ?>" class="auren-avatar-img" style="width:38px;height:38px;">
                            <?php else: ?>
                                <span class="auren-avatar"><?= htmlspecialchars($initials ?: '?') ?></span>
                            <?php endif; ?>
                            <span class="d-none d-lg-flex flex-column lh-1 text-start">
                                <span class="auren-avatar-name"><?= htmlspecialchars($name) ?></span>
                                <span class="auren-avatar-role"><?= htmlspecialchars($roleLabel) ?></span>
                            </span>
                            <i class="bi bi-chevron-down auren-avatar-caret d-none d-lg-inline"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end auren-profile-dropdown shadow" aria-labelledby="aurenProfileMenu">
                            <li class="px-3 py-2 d-lg-none">
                                <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($roleLabel) ?></div>
                            </li>
                            <li class="d-lg-none"><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($dashboardUrl) ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="/auren/account.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <?php if (!empty($roleLinks)): ?>
                                <li><hr class="dropdown-divider"></li>
                                <?php foreach ($roleLinks as [$href, $icon, $label]): ?>
                                    <li><a class="dropdown-item" href="<?= htmlspecialchars($href) ?>"><i class="bi <?= $icon ?> me-2"></i><?= htmlspecialchars($label) ?></a></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/auren/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-lg-2">
                        <a class="nav-link" href="/auren/auth/login.php">Sign in</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn auren-btn-primary" href="/auren/auth/register.php">Get started</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
