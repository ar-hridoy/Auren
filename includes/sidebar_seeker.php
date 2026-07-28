<?php
/**
 * includes/sidebar_seeker.php
 *
 * Dashboard sidebar for the Job Seeker role. Included by every page under
 * /seeker/. $activePage should be set by the including page so the
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
    <div class="auren-sidebar-heading">Job Seeker</div>
    <nav class="d-flex flex-column">
        <?= navItem('dashboard', $activePage, '/auren/seeker/dashboard.php', 'bi-speedometer2', 'Dashboard') ?>
        <?= navItem('browse_jobs', $activePage, '/auren/browse_jobs.php', 'bi-search', 'Browse Jobs') ?>
        <?= navItem('my_applications', $activePage, '/auren/seeker/my_applications.php', 'bi-send-check', 'My Applications') ?>
        <?= navItem('saved_jobs', $activePage, '/auren/seeker/saved_jobs.php', 'bi-bookmark-heart', 'Saved Jobs') ?>
        <?= navItem('resume', $activePage, '/auren/seeker/resume.php', 'bi-file-earmark-text', 'My Resume') ?>
        <?= navItem('account', $activePage, '/auren/account.php', 'bi-person-gear', 'Account Settings') ?>
    </nav>
</aside>
