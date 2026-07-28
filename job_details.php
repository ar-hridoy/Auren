<?php
/**
 * job_details.php
 *
 * Full detail view for a single open job, reachable by anyone. The Apply
 * panel adapts to who's looking:
 *   - guest            -> prompt to log in
 *   - employer         -> no apply panel (they can't apply to jobs; R3)
 *   - seeker, applied  -> "you've already applied" + status
 *   - seeker, no resume-> prompt to build a resume first (an application
 *                         must reference a resume; see Applications.resume_id)
 *   - seeker, ready    -> the actual apply form
 *
 * Incrementing job_views on each view is a deliberately small, non-critical
 * write; it is not part of any transaction and a failure to bump it would
 * never block the page.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

$jobId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT j.*, js.status_name,
            st.type_name AS salary_type, jt.job_type_name, c.category_name,
            a.area_name, d.district_name, dv.division_name,
            COALESCE(co.company_name, u.full_name) AS poster_name,
            co.company_id, co.description AS company_description, co.website
     FROM Jobs j
     JOIN Job_Statuses js ON j.status_id = js.status_id
     JOIN Salary_Types st ON j.salary_type_id = st.salary_type_id
     JOIN Job_Types jt ON j.job_type_id = jt.job_type_id
     JOIN Categories c ON j.category_id = c.category_id
     JOIN Areas a ON j.area_id = a.area_id
     JOIN Districts d ON a.district_id = d.district_id
     JOIN Divisions dv ON d.division_id = dv.division_id
     JOIN Users u ON j.employer_id = u.user_id
     LEFT JOIN Companies co ON j.company_id = co.company_id
     WHERE j.job_id = ? AND j.deleted_at IS NULL'
);
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    $pageTitle = 'Job not found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container py-5 text-center"><h1 class="h4">Job not found</h1>'
        . '<p class="text-muted">This job may have been removed or is no longer available.</p>'
        . '<a href="/auren/browse_jobs.php" class="btn auren-btn-primary mt-2">Back to browse</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Bump view count (non-critical).
$pdo->prepare('UPDATE Jobs SET job_views = job_views + 1 WHERE job_id = ?')->execute([$jobId]);

// Seeker-specific state
$isSeeker = isLoggedIn() && currentRole() === 'seeker';
$existingApplication = null;
$resume = null;
$isSaved = false;
if ($isSeeker) {
    $seekerId = currentUserId();

    $appStmt = $pdo->prepare(
        'SELECT a.application_id, ast.status_name
         FROM Applications a JOIN Application_Statuses ast ON a.status_id = ast.status_id
         WHERE a.job_id = ? AND a.seeker_id = ?'
    );
    $appStmt->execute([$jobId, $seekerId]);
    $existingApplication = $appStmt->fetch();

    $resumeStmt = $pdo->prepare('SELECT resume_id, headline FROM Resumes WHERE seeker_id = ?');
    $resumeStmt->execute([$seekerId]);
    $resume = $resumeStmt->fetch();

    $savedStmt = $pdo->prepare('SELECT 1 FROM Saved_Jobs WHERE seeker_id = ? AND job_id = ?');
    $savedStmt->execute([$seekerId, $jobId]);
    $isSaved = (bool) $savedStmt->fetchColumn();
}

$pageTitle = $job['title'];
require_once __DIR__ . '/includes/header.php';
renderFlash();
?>

<div class="container py-4">
    <a href="/auren/browse_jobs.php" class="small text-muted d-inline-block mb-3"><i class="bi bi-arrow-left"></i> Back to browse</a>

    <div class="row g-4">
        <!-- Main column -->
        <div class="col-lg-8">
            <div class="auren-card mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <span class="auren-badge auren-badge-open"><?= htmlspecialchars($job['job_type_name']) ?></span>
                    <?php if ($job['is_featured']): ?>
                        <span class="auren-badge auren-badge-pending"><i class="bi bi-star-fill"></i> Featured</span>
                    <?php endif; ?>
                </div>
                <h1 class="h3 fw-bold mb-1"><?= htmlspecialchars($job['title']) ?></h1>
                <p class="text-muted mb-3"><?= htmlspecialchars($job['poster_name']) ?></p>

                <div class="row g-3 mb-1">
                    <div class="col-sm-6"><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($job['area_name']) ?>, <?= htmlspecialchars($job['district_name']) ?>, <?= htmlspecialchars($job['division_name']) ?></div>
                    <div class="col-sm-6"><i class="bi bi-tag text-muted"></i> <?= htmlspecialchars($job['category_name']) ?></div>
                    <div class="col-sm-6"><i class="bi bi-cash text-muted"></i> Tk <?= number_format((float) $job['pay_rate'], 2) ?> / <?= htmlspecialchars($job['salary_type']) ?></div>
                    <div class="col-sm-6"><i class="bi bi-people text-muted"></i> <?= (int) $job['vacancies'] ?> vacancy(ies)</div>
                    <?php if (!empty($job['experience_required'])): ?>
                        <div class="col-sm-6"><i class="bi bi-briefcase text-muted"></i> <?= htmlspecialchars($job['experience_required']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($job['application_deadline'])): ?>
                        <div class="col-sm-6"><i class="bi bi-calendar-event text-muted"></i> Apply by <?= htmlspecialchars(date('M j, Y', strtotime($job['application_deadline']))) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="auren-card mb-3">
                <h2 class="h6 fw-bold">Job description</h2>
                <p style="white-space: pre-line;"><?= htmlspecialchars($job['description']) ?></p>

                <?php if (!empty($job['requirements'])): ?>
                    <h2 class="h6 fw-bold mt-3">Requirements</h2>
                    <p style="white-space: pre-line;"><?= htmlspecialchars($job['requirements']) ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($job['company_description'])): ?>
                <div class="auren-card">
                    <h2 class="h6 fw-bold">About <?= htmlspecialchars($job['poster_name']) ?></h2>
                    <p class="mb-1" style="white-space: pre-line;"><?= htmlspecialchars($job['company_description']) ?></p>
                    <?php if (!empty($job['website'])): ?>
                        <a href="<?= htmlspecialchars($job['website']) ?>" target="_blank" rel="noopener" class="small">Visit website <i class="bi bi-box-arrow-up-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Apply sidebar -->
        <div class="col-lg-4">
            <div class="auren-card position-sticky" style="top: 1rem;">
                <?php if (!isLoggedIn()): ?>
                    <h2 class="h6 fw-bold">Interested?</h2>
                    <p class="text-muted small">Log in or create a job seeker account to apply for this job.</p>
                    <a href="/auren/auth/login.php" class="btn auren-btn-primary w-100 mb-2">Log in to apply</a>
                    <a href="/auren/auth/register.php?role=seeker" class="btn btn-outline-secondary w-100">Create an account</a>

                <?php elseif (currentRole() === 'employer'): ?>
                    <h2 class="h6 fw-bold">Employer account</h2>
                    <p class="text-muted small mb-0">You're logged in as an employer, so you can't apply to jobs. Applications are for job seeker accounts.</p>

                <?php elseif ($existingApplication): ?>
                    <h2 class="h6 fw-bold">Application submitted</h2>
                    <p class="text-muted small">You've already applied to this job.</p>
                    <?php
                        $statusBadge = [
                            'pending' => 'auren-badge-pending',
                            'accepted' => 'auren-badge-accepted',
                            'rejected' => 'auren-badge-rejected',
                        ][$existingApplication['status_name']] ?? '';
                    ?>
                    <p class="mb-3">Status: <span class="auren-badge <?= $statusBadge ?>"><?= htmlspecialchars(ucfirst($existingApplication['status_name'])) ?></span></p>
                    <a href="/auren/seeker/my_applications.php" class="btn btn-outline-secondary w-100">View my applications</a>

                <?php elseif (!$resume): ?>
                    <h2 class="h6 fw-bold">Build a resume first</h2>
                    <p class="text-muted small">You need a resume on file before you can apply. It only takes a minute to set up.</p>
                    <a href="/auren/seeker/resume.php" class="btn auren-btn-primary w-100">Create my resume</a>

                <?php else: ?>
                    <h2 class="h6 fw-bold">Apply for this job</h2>
                    <p class="text-muted small">Your resume<?= $resume['headline'] ? ' (' . htmlspecialchars($resume['headline']) . ')' : '' ?> will be sent with your application.</p>
                    <form method="POST" action="/auren/seeker/apply.php">
                        <input type="hidden" name="job_id" value="<?= (int) $job['job_id'] ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Cover message <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea name="cover_message" class="form-control" rows="4" placeholder="Briefly introduce yourself and why you're a good fit..."></textarea>
                        </div>
                        <button type="submit" class="btn auren-btn-primary w-100 mb-2">Submit application</button>
                    </form>
                    <form method="POST" action="/auren/seeker/toggle_save.php">
                        <input type="hidden" name="job_id" value="<?= (int) $job['job_id'] ?>">
                        <input type="hidden" name="redirect" value="/auren/job_details.php?id=<?= (int) $job['job_id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="bi <?= $isSaved ? 'bi-bookmark-heart-fill' : 'bi-bookmark' ?>"></i>
                            <?= $isSaved ? 'Saved' : 'Save for later' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
