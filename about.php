<?php
/**
 * about.php — Public "About" page: mission, the problem Auren solves,
 * the values behind it, and the team. Static content, no auth required.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$values = [
    ['Temporary work comes first', 'Auren is designed around short-term hiring — not bolted onto a board built for permanent jobs. Every feature assumes fast posting, fast discovery, and fast application.'],
    ['Trust on both sides', 'Profiles are reviewed on sign-up, employers and seekers can be verified, and admins moderate listings so the marketplace stays credible as it grows.'],
    ['Honest by design', 'The platform only shows numbers it can back with real data. No fabricated review counts, no inflated stats — just what actually exists.'],
    ['Data integrity at the core', 'A normalized database enforces the rules: one application per job, one resume per seeker, and referential integrity everywhere, so the data stays clean.'],
];

// Real project team (from the proposal cover). Precompute initials.
$teamRaw = [
    ['Md. Abdur Rahim', 'Full Stack'],
    ['Saznin Tasnim Tahia', 'Database'],
    ['Md. Shahriar Hasan Sami ', 'Database'],
    ['Ishtiak Ahmed Akib ', 'Testing'],
    ['Adiba Binte Alam ', 'Frontend'],
];
$team = [];
foreach ($teamRaw as [$name, $role]) {
    $parts = preg_split('/\s+/', trim($name));
    $ini = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $last = $parts[count($parts) - 1];
        $ini .= strtoupper(substr($last, 0, 1));
    }
    $team[] = ['name' => $name, 'role' => $role, 'initials' => $ini];
}

$pageTitle = 'About';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auren-page-hero">
    <div class="container">
        <span class="auren-pill-badge mb-3"><i class="bi bi-stars"></i> Find work. Hire trust. Build tomorrow.</span>
        <h1>About Auren</h1>
        <p class="lead">Auren is a web-based marketplace built specifically for temporary employment — connecting employers who need short-term help with seekers who want flexible work.</p>
    </div>
</section>

<!-- Mission -->
<section class="auren-section">
    <div class="container" style="max-width: 820px;">
        <div class="auren-section-head">
            <span class="eyebrow">Our mission</span>
            <h2>Make short-term hiring simple and trustworthy</h2>
        </div>
        <p style="color: var(--auren-text-muted); font-size: 1.05rem; line-height: 1.75; text-align: center;">
            Temporary and gig-style work has become a normal way people earn income — through short contracts, seasonal
            roles, or single-day tasks. Yet most job portals are built around permanent, full-time positions, burying
            short-term listings and forcing seekers to sift through roles that were never meant for them. Auren closes
            that gap by treating temporary work as the primary use case, with a workflow tuned for speed on both sides.
        </p>
    </div>
</section>

<!-- Values -->
<section class="auren-section auren-section--tint">
    <div class="container" style="max-width: 900px;">
        <div class="auren-section-head">
            <span class="eyebrow">What we stand for</span>
            <h2>The principles behind Auren</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($values as [$title, $desc]): ?>
                <div class="col-md-6">
                    <div class="auren-value-row">
                        <span class="auren-value-check"><i class="bi bi-check-lg"></i></span>
                        <div>
                            <h4><?= htmlspecialchars($title) ?></h4>
                            <p><?= htmlspecialchars($desc) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Team -->
<section class="auren-section">
    <div class="container" style="max-width: 1000px;">
        <div class="auren-section-head">
            <span class="eyebrow">The team</span>
            <h2>Built by five students</h2>
            <p>A DBMS lab project at Daffodil International University (CSE312).</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($team as $member): ?>
                <div class="col-6 col-md-4 col-lg-2 text-center">
                    <span class="auren-avatar" style="width:64px;height:64px;font-size:1.3rem;margin-bottom:0.75rem;"><?= htmlspecialchars($member['initials']) ?></span>
                    <div style="font-weight:600;color:var(--auren-ink);font-size:0.92rem;line-height:1.25;"><?= htmlspecialchars($member['name']) ?></div>
                    <div class="text-muted" style="font-size:0.82rem;"><?= htmlspecialchars($member['role']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="auren-section auren-section--tint">
    <div class="container" style="max-width: 960px;">
        <div class="auren-cta-band">
            <h2>Join the Auren marketplace</h2>
            <p>Whether you're hiring or looking for work, get started in minutes.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="/auren/auth/register.php?role=seeker" class="auren-btn-white">Find work</a>
                <a href="/auren/auth/register.php?role=employer" class="auren-btn-ghost">Hire talent</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
