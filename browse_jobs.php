<?php
/**
 * browse_jobs.php
 *
 * Public job listing with filters. This page is intentionally reachable by
 * everyone (guests, seekers, even employers) — browsing is not gated. The
 * per-job actions that DO require an account (Apply, Save) are gated at the
 * point of action, not here, so a guest can look before signing up.
 *
 * Filters (category, area, job type, salary type, keyword) are applied as
 * additional WHERE clauses, built up as an array of conditions + bound
 * params so every value is still passed through a prepared statement — no
 * string interpolation of user input into SQL.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

// ---- Lookup data for the filter dropdowns ----
$categories = $pdo->query('SELECT category_id, category_name FROM Categories ORDER BY category_name')->fetchAll();
$jobTypes   = $pdo->query('SELECT job_type_id, job_type_name FROM Job_Types ORDER BY job_type_name')->fetchAll();
$salaryTypes= $pdo->query('SELECT salary_type_id, type_name FROM Salary_Types ORDER BY salary_type_id')->fetchAll();
$areas      = $pdo->query(
    'SELECT a.area_id, a.area_name, d.district_name
     FROM Areas a JOIN Districts d ON a.district_id = d.district_id
     ORDER BY d.district_name, a.area_name'
)->fetchAll();

// ---- Read filters from query string ----
$f = [
    'keyword'        => trim($_GET['keyword'] ?? ''),
    'category_id'    => $_GET['category_id'] ?? '',
    'area_id'        => $_GET['area_id'] ?? '',
    'job_type_id'    => $_GET['job_type_id'] ?? '',
    'salary_type_id' => $_GET['salary_type_id'] ?? '',
    'min_pay'        => $_GET['min_pay'] ?? '',
];

// Highest pay_rate among open jobs — used as the max for the pay slider.
$maxPay = (int) ceil((float) $pdo->query(
    "SELECT COALESCE(MAX(pay_rate), 1000) FROM Jobs j
     JOIN Job_Statuses js ON j.status_id = js.status_id
     WHERE js.status_name = 'open' AND j.deleted_at IS NULL"
)->fetchColumn());

// ---- Build the query dynamically, but safely (prepared params only) ----
$where = ["js.status_name = 'open'", 'j.deleted_at IS NULL'];
$params = [];

if ($f['keyword'] !== '') {
    $where[] = '(j.title LIKE ? OR j.description LIKE ?)';
    $params[] = '%' . $f['keyword'] . '%';
    $params[] = '%' . $f['keyword'] . '%';
}
if (ctype_digit((string) $f['category_id'])) {
    $where[] = 'j.category_id = ?';
    $params[] = (int) $f['category_id'];
}
if (ctype_digit((string) $f['area_id'])) {
    $where[] = 'j.area_id = ?';
    $params[] = (int) $f['area_id'];
}
if (ctype_digit((string) $f['job_type_id'])) {
    $where[] = 'j.job_type_id = ?';
    $params[] = (int) $f['job_type_id'];
}
if (ctype_digit((string) $f['salary_type_id'])) {
    $where[] = 'j.salary_type_id = ?';
    $params[] = (int) $f['salary_type_id'];
}
if (ctype_digit((string) $f['min_pay']) && (int) $f['min_pay'] > 0) {
    $where[] = 'j.pay_rate >= ?';
    $params[] = (int) $f['min_pay'];
}

$sql =
    'SELECT j.job_id, j.title, j.description, j.pay_rate, j.is_featured, j.created_at,
            st.type_name AS salary_type, jt.job_type_name, jt.job_type_id, c.category_name, c.category_id,
            a.area_name, d.district_name,
            u.is_verified,
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
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY j.is_featured DESC, j.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// ---- If this is a logged-in seeker, find which of these jobs they've
//      already saved or applied to, so the cards can reflect that state. ----
$savedJobIds = [];
$appliedJobIds = [];
if (isLoggedIn() && currentRole() === 'seeker' && !empty($jobs)) {
    $seekerId = currentUserId();
    $ids = array_column($jobs, 'job_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $savedStmt = $pdo->prepare("SELECT job_id FROM Saved_Jobs WHERE seeker_id = ? AND job_id IN ($placeholders)");
    $savedStmt->execute(array_merge([$seekerId], $ids));
    $savedJobIds = $savedStmt->fetchAll(PDO::FETCH_COLUMN);

    $appliedStmt = $pdo->prepare("SELECT job_id FROM Applications WHERE seeker_id = ? AND job_id IN ($placeholders)");
    $appliedStmt->execute(array_merge([$seekerId], $ids));
    $appliedJobIds = $appliedStmt->fetchAll(PDO::FETCH_COLUMN);
}

// ---- Helpers for the view ----

/**
 * Build a browse URL that merges the current filters with $overrides.
 * Passing null for a key drops it (used by "All" pills to clear a filter).
 */
$currentFilters = array_filter([
    'keyword'     => $f['keyword'],
    'category_id' => $f['category_id'],
    'area_id'     => $f['area_id'],
    'job_type_id' => $f['job_type_id'],
    'min_pay'     => $f['min_pay'],
], fn ($v) => $v !== '' && $v !== null);

$filterUrl = function (array $overrides) use ($currentFilters) {
    $merged = array_merge($currentFilters, $overrides);
    $merged = array_filter($merged, fn ($v) => $v !== '' && $v !== null);
    $qs = http_build_query($merged);
    return '/auren/browse_jobs.php' . ($qs ? '?' . $qs : '');
};

// Relative "time ago" from a timestamp.
$timeAgo = function (string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($ts));
};

// Initials for the poster avatar.
$initialsOf = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $ini = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $ini .= strtoupper(substr(end($parts), 0, 1));
    return $ini ?: '?';
};

$anyFilterActive = !empty($currentFilters);

$pageTitle = 'Browse Jobs';
require_once __DIR__ . '/includes/header.php';
renderFlash();
?>

<!-- Hero band -->
<div class="auren-browse-hero">
    <div class="container">
        <h1 class="auren-browse-title">Find your next gig</h1>
        <p class="auren-browse-sub"><?= count($jobs) ?> job<?= count($jobs) === 1 ? '' : 's' ?> available<?= $anyFilterActive ? ' · filtered' : '' ?></p>

        <!-- Search card -->
        <form method="GET" action="/auren/browse_jobs.php" class="auren-browse-search">
            <?php // preserve active non-keyword filters when searching ?>
            <?php foreach (['category_id', 'area_id', 'job_type_id', 'min_pay'] as $hidden): ?>
                <?php if ($f[$hidden] !== ''): ?>
                    <input type="hidden" name="<?= $hidden ?>" value="<?= htmlspecialchars($f[$hidden]) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="auren-search-inputwrap">
                <i class="bi bi-search"></i>
                <input type="text" name="keyword" placeholder="Search by title, employer or skill"
                    value="<?= htmlspecialchars($f['keyword']) ?>">
            </div>
            <select name="area_id" class="auren-search-select" onchange="this.form.submit()">
                <option value="">All areas</option>
                <?php foreach ($areas as $a): ?>
                    <option value="<?= $a['area_id'] ?>" <?= $f['area_id'] == $a['area_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['area_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="auren-search-btn">Search</button>
        </form>
    </div>
</div>

<div class="container auren-browse-body">
    <div class="row g-4">
        <!-- Filter sidebar -->
        <div class="col-lg-3">
            <div class="auren-filter-panel">
                <div class="auren-filter-head">
                    <span><i class="bi bi-sliders"></i> Filters</span>
                    <?php if ($anyFilterActive): ?>
                        <a href="/auren/browse_jobs.php" class="auren-filter-clear">Clear all</a>
                    <?php endif; ?>
                </div>

                <!-- Category -->
                <div class="auren-filter-group">
                    <div class="auren-filter-label">Category</div>
                    <div class="auren-pill-row">
                        <a href="<?= htmlspecialchars($filterUrl(['category_id' => null])) ?>"
                           class="auren-pill <?= $f['category_id'] === '' ? 'active' : '' ?>">All</a>
                        <?php foreach ($categories as $c): ?>
                            <a href="<?= htmlspecialchars($filterUrl(['category_id' => $c['category_id']])) ?>"
                               class="auren-pill <?= $f['category_id'] == $c['category_id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($c['category_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Job type (duration) -->
                <div class="auren-filter-group">
                    <div class="auren-filter-label">Job Type</div>
                    <div class="auren-pill-row">
                        <a href="<?= htmlspecialchars($filterUrl(['job_type_id' => null])) ?>"
                           class="auren-pill <?= $f['job_type_id'] === '' ? 'active' : '' ?>">All</a>
                        <?php foreach ($jobTypes as $jt): ?>
                            <a href="<?= htmlspecialchars($filterUrl(['job_type_id' => $jt['job_type_id']])) ?>"
                               class="auren-pill <?= $f['job_type_id'] == $jt['job_type_id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($jt['job_type_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Minimum pay -->
                <form method="GET" action="/auren/browse_jobs.php" class="auren-filter-group" id="payForm">
                    <?php foreach (['keyword', 'category_id', 'area_id', 'job_type_id'] as $hidden): ?>
                        <?php if ($f[$hidden] !== ''): ?>
                            <input type="hidden" name="<?= $hidden ?>" value="<?= htmlspecialchars($f[$hidden]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div class="auren-filter-label">
                        Minimum pay: <span id="payLabel">Tk <?= number_format((int) ($f['min_pay'] ?: 0)) ?></span>
                    </div>
                    <input type="range" name="min_pay" min="0" max="<?= $maxPay ?>" step="100"
                        value="<?= (int) ($f['min_pay'] ?: 0) ?>" class="auren-range"
                        oninput="document.getElementById('payLabel').textContent = 'Tk ' + Number(this.value).toLocaleString()"
                        onchange="document.getElementById('payForm').submit()">
                    <div class="auren-range-scale"><span>Tk 0</span><span>Tk <?= number_format($maxPay) ?></span></div>
                </form>
            </div>
        </div>

        <!-- Job grid -->
        <div class="col-lg-9">
            <?php if (empty($jobs)): ?>
                <div class="auren-card text-center py-5">
                    <i class="bi bi-search text-muted" style="font-size:2rem;"></i>
                    <p class="text-muted mb-2 mt-2">No jobs match your filters.</p>
                    <a href="/auren/browse_jobs.php" class="btn auren-btn-primary btn-sm">Clear filters</a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($jobs as $job): ?>
                        <?php
                            $isSaved = in_array($job['job_id'], $savedJobIds);
                            $isApplied = in_array($job['job_id'], $appliedJobIds);
                        ?>
                        <div class="col-12 col-xl-6">
                            <div class="auren-job-card">
                                <!-- header: avatar + poster + time, verified -->
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

                                <!-- category chip -->
                                <span class="auren-cat-chip"><?= htmlspecialchars($job['category_name']) ?></span>

                                <!-- title -->
                                <h2 class="auren-job-title">
                                    <a href="/auren/job_details.php?id=<?= (int) $job['job_id'] ?>"><?= htmlspecialchars($job['title']) ?></a>
                                </h2>

                                <!-- meta grid -->
                                <div class="auren-job-meta">
                                    <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($job['area_name']) ?>, <?= htmlspecialchars($job['district_name']) ?></span>
                                    <span><i class="bi bi-clock"></i> <?= htmlspecialchars($job['job_type_name']) ?></span>
                                    <span><i class="bi bi-cash-coin"></i> Tk <?= number_format((float) $job['pay_rate']) ?>/<?= htmlspecialchars($job['salary_type']) ?></span>
                                    <span><i class="bi bi-people"></i> <?= (int) $job['applicant_count'] ?> applicant<?= (int) $job['applicant_count'] === 1 ? '' : 's' ?></span>
                                </div>

                                <!-- actions -->
                                <div class="auren-job-actions">
                                    <a href="/auren/job_details.php?id=<?= (int) $job['job_id'] ?>" class="auren-view-btn">
                                        <?= $isApplied ? 'View (applied)' : 'View details' ?>
                                    </a>
                                    <?php if (isLoggedIn() && currentRole() === 'seeker'): ?>
                                        <form method="POST" action="/auren/seeker/toggle_save.php">
                                            <input type="hidden" name="job_id" value="<?= (int) $job['job_id'] ?>">
                                            <input type="hidden" name="redirect" value="/auren/browse_jobs.php">
                                            <button type="submit" class="auren-save-btn" title="<?= $isSaved ? 'Saved' : 'Save job' ?>">
                                                <i class="bi <?= $isSaved ? 'bi-bookmark-heart-fill' : 'bi-bookmark' ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
