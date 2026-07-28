<?php
/**
 * includes/sidebar_employer.php
 *
 * Dashboard sidebar for the Employer role. Included by every page under
 * /employer/. $activePage should be set by the including page (e.g.
 * 'dashboard', 'post_job', 'my_jobs', 'company_profile') so the matching
 * nav item gets the .active class.
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
    <div class="auren-sidebar-heading">Employer</div>
    <nav class="d-flex flex-column">
        <?= navItem('dashboard', $activePage, '/auren/employer/dashboard.php', 'bi-speedometer2', 'Dashboard') ?>
        <?= navItem('post_job', $activePage, '/auren/employer/post_job.php', 'bi-plus-square', 'Post a Job') ?>
        <?= navItem('my_jobs', $activePage, '/auren/employer/my_jobs.php', 'bi-briefcase', 'My Jobs') ?>
        <?= navItem('applicants', $activePage, '/auren/employer/applicants.php', 'bi-people', 'Applicants') ?>
        <?= navItem('company_profile', $activePage, '/auren/employer/company_profile.php', 'bi-building', 'Company Profile') ?>
        <?= navItem('account', $activePage, '/auren/account.php', 'bi-person-gear', 'Account Settings') ?>
    </nav>
</aside>
