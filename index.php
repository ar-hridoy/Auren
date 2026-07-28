<?php
/**
 * index.php — Landing page.
 *
 * Polish pass (originally scheduled for Phase 7, pulled forward since we
 * now have real Phase 1-3 data to show instead of placeholder numbers).
 * Every number and job listing on this page is a live query — nothing
 * here is hardcoded, unlike the QuickHire reference this design was
 * inspired by, which used fabricated stats ("4.9 average from 84,000+
 * reviews"). We only show numbers we can actually back with real rows.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

// ---- Live stats for the bottom strip ----
$activeJobs = (int) $pdo->query(
    "SELECT COUNT(*) FROM Jobs j JOIN Job_Statuses js ON j.status_id = js.status_id WHERE js.status_name = 'open'"
)->fetchColumn();
$employerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Users u JOIN Roles r ON u.role_id = r.role_id WHERE r.role_name = 'employer'"
)->fetchColumn();
$seekerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Users u JOIN Roles r ON u.role_id = r.role_id WHERE r.role_name = 'seeker'"
)->fetchColumn();
$areaCount = (int) $pdo->query("SELECT COUNT(*) FROM Areas")->fetchColumn();

// ---- Floating badge numbers (real, not fabricated) ----
$employersHiringNow = (int) $pdo->query(
    "SELECT COUNT(DISTINCT j.employer_id) FROM Jobs j
     JOIN Job_Statuses js ON j.status_id = js.status_id WHERE js.status_name = 'open'"
)->fetchColumn();
$filledThisMonth = (int) $pdo->query(
    "SELECT COUNT(*) FROM Jobs j JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE js.status_name = 'filled' AND j.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
)->fetchColumn();

// ---- Three real open jobs for the hero preview panel ----
$previewJobs = $pdo->query(
    "SELECT j.title, a.area_name,
            COALESCE(c.company_name, u.full_name) AS poster_name,
            j.pay_rate, st.type_name AS salary_type
     FROM Jobs j
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies c ON j.company_id = c.company_id
     JOIN Areas a ON j.area_id = a.area_id
     JOIN Salary_Types st ON j.salary_type_id = st.salary_type_id
     JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE js.status_name = 'open'
     ORDER BY j.is_featured DESC, j.created_at DESC
     LIMIT 3"
)->fetchAll();

// ---- Six real open jobs for the "Featured jobs" grid ----
$featuredJobs = $pdo->query(
    "SELECT j.job_id, j.title, j.pay_rate, j.is_featured, j.created_at,
            st.type_name AS salary_type, jt.job_type_name, c.category_name,
            a.area_name, d.district_name, u.is_verified,
            COALESCE(co.company_name, u.full_name) AS poster_name,
            (SELECT COUNT(*) FROM Applications ap WHERE ap.job_id = j.job_id) AS applicant_count
     FROM Jobs j
     JOIN Job_Statuses js ON j.status_id = js.status_id
     JOIN Salary_Types st ON j.salary_type_id = st.salary_type_id
     JOIN Job_Types jt ON j.job_type_id = jt.job_type_id
     JOIN Categories c ON j.category_id = c.category_id
     JOIN Areas a ON j.area_id = a.area_id
     JOIN Districts d ON a.district_id = d.district_id
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies co ON j.company_id = co.company_id
     WHERE js.status_name = 'open' AND j.deleted_at IS NULL
     ORDER BY j.is_featured DESC, j.created_at DESC
     LIMIT 6"
)->fetchAll();

// ---- Top categories by open-job count (for the "Browse by category" band) ----
$topCategories = $pdo->query(
    "SELECT c.category_id, c.category_name, COUNT(j.job_id) AS job_count
     FROM Categories c
     LEFT JOIN Jobs j ON j.category_id = c.category_id
        AND j.deleted_at IS NULL
        AND j.status_id = (SELECT status_id FROM Job_Statuses WHERE status_name = 'open')
     GROUP BY c.category_id, c.category_name
     ORDER BY job_count DESC, c.category_name ASC
     LIMIT 8"
)->fetchAll();

// View helpers (shared with the sections below)
$timeAgo = function (string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($ts));
};
$initialsOf = function (string $name): string {
    $p = preg_split('/\s+/', trim($name));
    $i = strtoupper(substr($p[0], 0, 1));
    if (count($p) > 1) $i .= strtoupper(substr($p[count($p) - 1], 0, 1));
    return $i ?: '?';
};

// Category icon lookup (falls back to a generic tag icon)
$catIcons = [
    'Cleaning Services' => 'bi-stars', 'Delivery & Errands' => 'bi-truck',
    'Event Staffing' => 'bi-people', 'Tutoring & Teaching' => 'bi-mortarboard',
    'Home Repair & Maintenance' => 'bi-tools', 'IT & Tech Support' => 'bi-laptop',
    'Photography & Media' => 'bi-camera', 'Moving & Labor' => 'bi-box-seam',
    'Data Entry' => 'bi-keyboard', 'Graphic Design' => 'bi-palette',
];

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
renderFlash();
?>

<section class="auren-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <span class="auren-pill-badge mb-3">
                    <i class="bi bi-geo-alt-fill"></i> Now serving Dhaka and nearby areas
                </span>
                <h1 class="fw-bold mb-3">
                    Find temporary work or hire
                    <span class="accent">trusted talent instantly.</span>
                </h1>
                <p class="lead text-muted mb-4">
                    Auren connects employers and job seekers for short-term work —
                    hourly, daily, weekly, monthly, or contract. Post a job in minutes,
                    or find flexible work near you today.
                </p>
                <div class="d-flex gap-3 mb-4 flex-wrap">
                    <a href="/auren/browse_jobs.php" class="btn auren-btn-primary px-4 py-2">
                        Find jobs <i class="bi bi-arrow-right"></i>
                    </a>
                    <a href="/auren/auth/register.php?role=employer" class="btn btn-outline-secondary px-4 py-2">
                        Post a job
                    </a>
                </div>
                <div class="auren-trust-row">
                    <span><i class="bi bi-patch-check-fill"></i> Profiles reviewed on sign-up</span>
                    <span><i class="bi bi-lightning-charge-fill"></i> Post or apply in minutes</span>
                </div>
            </div>

            <div class="col-lg-6 mt-5 mt-lg-0">
                <div style="position: relative; max-width: 460px; margin: 0 auto;">
                    <!-- Floating badge: employers hiring right now -->
                    <div class="auren-floating-badge auren-floating-badge--top">
                        <div class="fb-icon" style="background: var(--auren-chip-blue); color: var(--auren-primary);">
                            <i class="bi bi-briefcase-fill"></i>
                        </div>
                        <div>
                            <div class="fb-number"><?= $employersHiringNow ?> employer<?= $employersHiringNow === 1 ? '' : 's' ?></div>
                            <div class="fb-label">hiring right now</div>
                        </div>
                    </div>

                    <div class="auren-hero-panel">
                        <div class="auren-hero-search">
                            <i class="bi bi-search"></i> Search "weekend tutor"...
                        </div>

                        <?php if (empty($previewJobs)): ?>
                            <p class="text-muted small mb-0">No open jobs yet — be the first to post one.</p>
                        <?php else: ?>
                            <?php foreach ($previewJobs as $job): ?>
                                <div class="auren-job-row">
                                    <div class="auren-avatar-circle">
                                        <?= htmlspecialchars(strtoupper(substr($job['poster_name'], 0, 1))) ?>
                                    </div>
                                    <div class="flex-grow-1" style="min-width:0;">
                                        <div class="auren-job-row-title text-truncate"><?= htmlspecialchars($job['title']) ?></div>
                                        <div class="auren-job-row-meta text-truncate">
                                            <?= htmlspecialchars($job['poster_name']) ?> · <?= htmlspecialchars($job['area_name']) ?>
                                        </div>
                                    </div>
                                    <div class="auren-job-row-price">
                                        Tk <?= number_format((float) $job['pay_rate']) ?>/<?= htmlspecialchars($job['salary_type']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Floating badge: jobs filled this month -->
                    <div class="auren-floating-badge auren-floating-badge--bottom">
                        <div class="fb-icon" style="background:#E7F6EC; color:#1E7A3D;">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div>
                            <div class="fb-number"><?= $filledThisMonth ?> filled</div>
                            <div class="fb-label">in the last 30 days</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="auren-stat-strip">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3">
                <div class="stat-number"><?= $activeJobs ?>+</div>
                <div class="stat-label">Active jobs</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number"><?= $employerCount ?>+</div>
                <div class="stat-label">Registered employers</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number"><?= $seekerCount ?>+</div>
                <div class="stat-label">Registered seekers</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number"><?= $areaCount ?></div>
                <div class="stat-label">Areas covered</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FEATURED JOBS ============ -->
<section class="auren-section auren-section--tint">
    <div class="container" style="max-width: 1200px;">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
            <div>
                <span class="eyebrow d-block" style="text-transform:uppercase;letter-spacing:0.08em;font-size:0.78rem;font-weight:700;color:var(--auren-primary);margin-bottom:0.5rem;">Featured Jobs</span>
                <h2 style="font-size:2rem;font-weight:700;letter-spacing:-0.03em;color:var(--auren-ink);margin-bottom:0.4rem;">Fresh opportunities, posted recently</h2>
                <p class="text-muted mb-0" style="font-size:1.02rem;">Hand-picked gigs from employers across our top categories.</p>
            </div>
            <a href="/auren/browse_jobs.php" class="btn auren-btn-soft">View all jobs <i class="bi bi-arrow-right"></i></a>
        </div>

        <?php if (empty($featuredJobs)): ?>
            <div class="auren-card text-center py-5">
                <i class="bi bi-briefcase text-muted" style="font-size:2rem;"></i>
                <p class="text-muted mb-0 mt-2">No open jobs yet — check back soon.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($featuredJobs as $job): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="auren-job-card">
                            <div class="auren-job-top">
                                <div class="auren-job-poster">
                                    <span class="auren-avatar"><?= htmlspecialchars($initialsOf($job['poster_name'])) ?></span>
                                    <div>
                                        <div class="auren-job-company"><?= htmlspecialchars($job['poster_name']) ?></div>
                                        <div class="auren-job-time"><?= htmlspecialchars($timeAgo($job['created_at'])) ?></div>
                                    </div>
                                </div>
                                <?php if ($job['is_verified']): ?>
                                    <span class="auren-verified"><i class="bi bi-patch-check-fill"></i> Verified</span>
                                <?php elseif ($job['is_featured']): ?>
                                    <span class="auren-verified auren-featured"><i class="bi bi-star-fill"></i> Featured</span>
                                <?php endif; ?>
                            </div>
                            <span class="auren-cat-chip"><?= htmlspecialchars($job['category_name']) ?></span>
                            <h3 class="auren-job-title">
                                <a href="/auren/job_details.php?id=<?= (int) $job['job_id'] ?>"><?= htmlspecialchars($job['title']) ?></a>
                            </h3>
                            <div class="auren-job-meta">
                                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($job['area_name']) ?>, <?= htmlspecialchars($job['district_name']) ?></span>
                                <span><i class="bi bi-clock"></i> <?= htmlspecialchars($job['job_type_name']) ?></span>
                                <span><i class="bi bi-cash-coin"></i> Tk <?= number_format((float) $job['pay_rate']) ?>/<?= htmlspecialchars($job['salary_type']) ?></span>
                                <span><i class="bi bi-people"></i> <?= (int) $job['applicant_count'] ?> applicant<?= (int) $job['applicant_count'] === 1 ? '' : 's' ?></span>
                            </div>
                            <div class="auren-job-actions">
                                <a href="/auren/job_details.php?id=<?= (int) $job['job_id'] ?>" class="auren-view-btn">View details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============ BROWSE BY CATEGORY ============ -->
<section class="auren-section">
    <div class="container" style="max-width: 1100px;">
        <div class="auren-section-head">
            <span class="eyebrow">Browse by category</span>
            <h2>Find work in your field</h2>
            <p>Explore openings across the categories people hire for most.</p>
        </div>
        <div class="row g-3">
            <?php foreach ($topCategories as $cat): ?>
                <?php $icon = $catIcons[$cat['category_name']] ?? 'bi-tag'; ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="/auren/browse_jobs.php?category_id=<?= (int) $cat['category_id'] ?>" class="auren-cat-card">
                        <span class="auren-cat-icon"><i class="bi <?= $icon ?>"></i></span>
                        <div class="auren-cat-name"><?= htmlspecialchars($cat['category_name']) ?></div>
                        <div class="auren-cat-count"><?= (int) $cat['job_count'] ?> open job<?= (int) $cat['job_count'] === 1 ? '' : 's' ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ WHY AUREN ============ -->
<section class="auren-section auren-section--tint">
    <div class="container" style="max-width: 1080px;">
        <div class="auren-section-head">
            <span class="eyebrow">Why Auren</span>
            <h2>Built for temporary work, end to end</h2>
            <p>Everything short-term hiring needs, and nothing it doesn't.</p>
        </div>
        <div class="row g-4">
            <?php
            $why = [
                ['bi-lightning-charge-fill', 'Post or apply in minutes', 'A focused flow means employers publish fast and seekers apply in one step — no endless forms.'],
                ['bi-patch-check-fill', 'Verified & reviewed', 'Profiles are reviewed on sign-up, and admins verify trustworthy accounts so both sides hire with confidence.'],
                ['bi-sliders', 'Flexible durations', 'Hourly, daily, weekly, monthly, or contract — Auren is designed around every kind of short-term role.'],
                ['bi-clipboard-check-fill', 'Track everything', 'Applications, statuses, saved jobs, and resumes all live in one clean dashboard for each role.'],
                ['bi-geo-alt-fill', 'Local first', 'Jobs are organized by area across a full geographic hierarchy, so you find work close to home.'],
                ['bi-shield-lock-fill', 'Data you can trust', 'A normalized database enforces the rules — one application per job, one resume per seeker, no duplicates.'],
            ];
            foreach ($why as [$icon, $title, $desc]): ?>
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

<!-- ============ HOW IT WORKS (teaser) ============ -->
<section class="auren-section">
    <div class="container" style="max-width: 1000px;">
        <div class="auren-section-head">
            <span class="eyebrow">How it works</span>
            <h2>Three steps to get going</h2>
            <p>The same simple path, whether you're hiring or looking for work.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4 text-center">
                <span class="auren-step-num mx-auto mb-3" style="position:static;">1</span>
                <h3 style="font-size:1.15rem;font-weight:700;color:var(--auren-ink);">Create an account</h3>
                <p class="text-muted" style="font-size:0.95rem;">Sign up as a seeker or an employer in under a minute.</p>
            </div>
            <div class="col-md-4 text-center">
                <span class="auren-step-num mx-auto mb-3" style="position:static;">2</span>
                <h3 style="font-size:1.15rem;font-weight:700;color:var(--auren-ink);">Post or apply</h3>
                <p class="text-muted" style="font-size:0.95rem;">Publish a job in minutes, or apply with your resume in one step.</p>
            </div>
            <div class="col-md-4 text-center">
                <span class="auren-step-num mx-auto mb-3" style="position:static;">3</span>
                <h3 style="font-size:1.15rem;font-weight:700;color:var(--auren-ink);">Connect & get it done</h3>
                <p class="text-muted" style="font-size:0.95rem;">Review applicants, accept the right person, and track status live.</p>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="/auren/how_it_works.php" class="btn auren-btn-soft">See the full walkthrough <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- ============ FINAL CTA ============ -->
<section class="auren-section auren-section--tint">
    <div class="container" style="max-width: 960px;">
        <div class="auren-cta-band">
            <h2>Ready to find work or hire talent?</h2>
            <p>Join Auren and get started in minutes — it's free to sign up.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="/auren/auth/register.php?role=seeker" class="auren-btn-white">Find work</a>
                <a href="/auren/auth/register.php?role=employer" class="auren-btn-ghost">Hire talent</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
