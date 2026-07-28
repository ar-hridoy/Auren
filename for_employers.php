<?php
/**
 * for_employers.php — Public marketing page aimed at employers. Explains
 * the value of posting on Auren and links into the employer sign-up /
 * post-a-job flow. Live totals keep the stats honest.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$employerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Users u JOIN Roles r ON u.role_id = r.role_id WHERE r.role_name = 'employer'"
)->fetchColumn();
$seekerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Users u JOIN Roles r ON u.role_id = r.role_id WHERE r.role_name = 'seeker'"
)->fetchColumn();
$areaCount = (int) $pdo->query("SELECT COUNT(*) FROM Areas")->fetchColumn();

$benefits = [
    ['bi-lightning-charge-fill', 'Post in minutes', 'Create a listing with a title, description, pay rate, category, and area — and publish immediately. No lengthy setup.'],
    ['bi-people-fill', 'One applicant pool', 'Every application for a job lands in one place. Open resumes, review skills, and decide without chasing emails.'],
    ['bi-patch-check-fill', 'Verified profiles', 'Accounts are reviewed on sign-up, and admins can verify trustworthy employers and seekers so both sides hire with confidence.'],
    ['bi-sliders', 'Flexible pay & duration', 'Hire hourly, daily, weekly, monthly, or on contract. Auren is built specifically for short-term and temporary work.'],
    ['bi-clipboard-check-fill', 'Simple decisions', 'Accept or reject applicants with a click. Their status updates instantly, so nobody is left waiting.'],
    ['bi-building', 'Company profile', 'Add a company profile with your logo, industry, and size to stand out and build recognition across your listings.'],
];

$pageTitle = 'For Employers';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auren-page-hero">
    <div class="container">
        <span class="auren-pill-badge mb-3"><i class="bi bi-briefcase-fill"></i> For Employers</span>
        <h1>Hire trusted talent, in hours</h1>
        <p class="lead">Auren gives you a dedicated space to post short-term work and manage applicants — without the friction of a traditional job board built for permanent roles.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/auren/auth/register.php?role=employer" class="btn auren-btn-primary px-4 py-2">Post a job <i class="bi bi-arrow-right"></i></a>
            <a href="/auren/how_it_works.php" class="btn btn-outline-secondary px-4 py-2">See how it works</a>
        </div>
    </div>
</section>

<!-- Live stats -->
<section class="auren-section" style="padding-bottom: 1rem;">
    <div class="container" style="max-width: 900px;">
        <div class="row g-4">
            <div class="col-6 col-md-4"><div class="auren-mini-stat"><div class="num"><?= $seekerCount ?>+</div><div class="lbl">Registered seekers</div></div></div>
            <div class="col-6 col-md-4"><div class="auren-mini-stat"><div class="num"><?= $employerCount ?>+</div><div class="lbl">Registered employers</div></div></div>
            <div class="col-12 col-md-4"><div class="auren-mini-stat"><div class="num"><?= $areaCount ?></div><div class="lbl">Areas covered</div></div></div>
        </div>
    </div>
</section>

<!-- Benefits -->
<section class="auren-section">
    <div class="container" style="max-width: 1080px;">
        <div class="auren-section-head">
            <span class="eyebrow">Why Auren</span>
            <h2>Everything you need to hire short-term</h2>
            <p>Built around the workflow temporary hiring actually needs.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($benefits as [$icon, $title, $desc]): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="auren-feature-card">
                        <span class="auren-feature-icon"><i class="bi <?= $icon ?>"></i></span>
                        <h3><?= htmlspecialchars($title) ?></h3>
                        <p><?= htmlspecialchars($desc) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="auren-section auren-section--tint">
    <div class="container" style="max-width: 960px;">
        <div class="auren-cta-band">
            <h2>Post your first job today</h2>
            <p>It takes a few minutes, and your listing reaches seekers across <?= $areaCount ?> areas.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="/auren/auth/register.php?role=employer" class="auren-btn-white">Get started free</a>
                <a href="/auren/browse_jobs.php" class="auren-btn-ghost">Browse the marketplace</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
