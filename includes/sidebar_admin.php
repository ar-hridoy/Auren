<?php
/**
 * includes/sidebar_admin.php
 *
 * Dashboard sidebar for the Admin role. Included by every page under
 * /admin/. $activePage should be set by the including page so the
 * matching nav item gets the .active class.
 */
$activePage = $activePage ?? '';

if (!function_exists('navItem')) {
    function navItem(string $key, string $active, string $href, string $icon, string $label): string
    {
        $isActive = ($key === $active) ? 'active' : '';
        return '<a href="' . htmlspecialchars($href) . '" class="auren-side-link ' . $isActive . '">'
            . '<i class="bi ' . $icon . '"></i> ' . htmlspecialchars($label) . '</a>';
    }
}
?>
<aside class="auren-sidebar">
    <div class="auren-sidebar-heading">Admin</div>
    <nav class="d-flex flex-column">
        <?= navItem('dashboard', $activePage, '/auren/admin/dashboard.php', 'bi-speedometer2', 'Overview') ?>
        <?= navItem('users', $activePage, '/auren/admin/users.php', 'bi-people', 'Users') ?>
        <?= navItem('jobs', $activePage, '/auren/admin/jobs.php', 'bi-briefcase', 'Job Moderation') ?>
        <?= navItem('categories', $activePage, '/auren/admin/categories.php', 'bi-tags', 'Categories & Skills') ?>
    </nav>
</aside>
