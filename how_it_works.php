<?php
/**
 * how_it_works.php — Public marketing page explaining the Auren flow for
 * both sides of the marketplace. Static content (no auth required); pulls
 * a couple of live totals from the database so the numbers stay honest.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$jobCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Jobs j JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE js.status_name = 'open' AND j.deleted_at IS NULL"
)->fetchColumn();
$categoryCount = (int) $pdo->query("SELECT COUNT(*) FROM Categories")->fetchColumn();

$seekerSteps = [
    ['1', 'Create your profile', 'Sign up as a job seeker and build a single, reusable resume with your headline, experience, and skills — no re-typing for every application.'],
    ['2', 'Browse & filter jobs', 'Search open roles and narrow by category, area, job type, and minimum pay to find short-term work that fits your schedule.'],
    ['3', 'Apply in one step', 'Send your resume with an optional cover message. Auren stops you from accidentally applying to the same job twice.'],
    ['4', 'Track every application', 'Watch each application move from pending to accepted or rejected from your dashboard — no guessing where you stand.'],
];
$employerSteps = [
    ['1', 'Register your account', 'Sign up as an employer — as an individual or a company — and optionally add a company profile with your logo and industry.'],
    ['2', 'Post a job in minutes', 'Describe the work, set the pay rate and type (hourly, daily, weekly, monthly, or contract), pick a category and area, and publish.'],
    ['3', 'Review applicants', 'See everyone who applied, open their resume and skills, and manage a fast-moving applicant pool from one place.'],
    ['4', 'Accept the right person', 'Accept or reject applications with a click. Applicants are notified of their status instantly on their dashboard.'],
];

$pageTitle = 'How It Works';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auren-page-hero">
    <div class="container">
        <span class="auren-pill-badge mb-3"><i class="bi bi-signpost-2-fill"></i> Simple for both sides</span>
        <h1>How Auren works</h1>
        <p class="lead">Whether you're looking for temporary work or hiring for it, Auren keeps the process short: post or apply in minutes, then manage everything from one dashboard.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/auren/browse_jobs.php" class="btn auren-btn-primary px-4 py-2">Find jobs <i class="bi bi-arrow-right"></i></a>
            <a href="/auren/auth/register.php?role=employer" class="btn btn-outline-secondary px-4 py-2">Post a job</a>
        </div>
    </div>
</section>

<!-- For seekers -->
<section class="auren-section">
    <div class="container" style="max-width: 1080px;">
        <div class="auren-section-head">
            <span class="eyebrow">For Job Seekers</span>
            <h2>Find and land short-term work</h2>
            <p>From sign-up to accepted in four steps.</p>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <?php foreach (array_slice($seekerSteps, 0, 2) as [$n, $h, $p]): ?>
                    <div class="auren-step">
                        <div class="auren-step-num"><?= $n ?></div>
                        <h3><?= htmlspecialchars($h) ?></h3>
                        <p><?= htmlspecialchars($p) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="col-lg-6">
                <?php foreach (array_slice($seekerSteps, 2) as [$n, $h, $p]): ?>
                    <div class="auren-step">
                        <div class="auren-step-num"><?= $n ?></div>
                        <h3><?= htmlspecialchars($h) ?></h3>
                        <p><?= htmlspecialchars($p) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- For employers -->
<section class="auren-section auren-section--tint">
    <div class="container" style="max-width: 1080px;">
        <div class="auren-section-head">
            <span class="eyebrow">For Employers</span>
            <h2>Hire trusted talent, fast</h2>
            <p>Post once, review applicants, and fill the role.</p>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <?php foreach (array_slice($employerSteps, 0, 2) as [$n, $h, $p]): ?>
                    <div class="auren-step">
                        <div class="auren-step-num"><?= $n ?></div>
                        <h3><?= htmlspecialchars($h) ?></h3>
                        <p><?= htmlspecialchars($p) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="col-lg-6">
                <?php foreach (array_slice($employerSteps, 2) as [$n, $h, $p]): ?>
                    <div class="auren-step">
                        <div class="auren-step-num"><?= $n ?></div>
                        <h3><?= htmlspecialchars($h) ?></h3>
                        <p><?= htmlspecialchars($p) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="auren-section">
    <div class="container" style="max-width: 960px;">
        <div class="auren-cta-band">
            <h2>Ready to get started?</h2>
            <p><?= $jobCount ?> open job<?= $jobCount === 1 ? '' : 's' ?> across <?= $categoryCount ?> categories are waiting.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="/auren/auth/register.php?role=seeker" class="auren-btn-white">Find work</a>
                <a href="/auren/auth/register.php?role=employer" class="auren-btn-ghost">Hire talent</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
