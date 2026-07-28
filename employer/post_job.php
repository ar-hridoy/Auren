<?php
/**
 * employer/post_job.php
 *
 * Job creation form. Only Employers can reach this page (requireRole),
 * which is Business Rule R2/R7 enforced twice over: here at the page-access
 * level, and again at the database level since Jobs.employer_id can only
 * reference a row that exists in Employers.
 *
 * If this employer has already created a Company profile, the job is
 * automatically associated with it (company_id set); if not, the job is
 * posted under their personal name and they're nudged toward setting one
 * up, matching the nullable Jobs.company_id design.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('employer');

$employerId = currentUserId();

// Does this employer already have a Company profile?
$companyStmt = $pdo->prepare('SELECT company_id, company_name FROM Companies WHERE employer_id = ?');
$companyStmt->execute([$employerId]);
$company = $companyStmt->fetch();

// ---- Lookup data for the form ----
$categories = $pdo->query('SELECT category_id, category_name FROM Categories ORDER BY category_name')->fetchAll();
$salaryTypes = $pdo->query('SELECT salary_type_id, type_name FROM Salary_Types ORDER BY salary_type_id')->fetchAll();
$jobTypes = $pdo->query('SELECT job_type_id, job_type_name FROM Job_Types ORDER BY job_type_name')->fetchAll();
$areas = $pdo->query(
    'SELECT a.area_id, a.area_name, d.district_name
     FROM Areas a JOIN Districts d ON a.district_id = d.district_id
     ORDER BY d.district_name, a.area_name'
)->fetchAll();

$errors = [];
$old = [
    'title' => '', 'description' => '', 'requirements' => '',
    'category_id' => '', 'area_id' => '', 'salary_type_id' => '', 'job_type_id' => '',
    'pay_rate' => '', 'vacancies' => '1', 'experience_required' => '',
    'application_deadline' => '', 'start_date' => '', 'end_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    // ---- Validation ----
    if (strlen($old['title']) < 5) {
        $errors[] = 'Job title must be at least 5 characters.';
    }
    if (strlen($old['description']) < 20) {
        $errors[] = 'Please provide a more detailed description (at least 20 characters).';
    }
    if (!ctype_digit($old['category_id'])) {
        $errors[] = 'Please choose a category.';
    }
    if (!ctype_digit($old['area_id'])) {
        $errors[] = 'Please choose a location.';
    }
    if (!ctype_digit($old['salary_type_id'])) {
        $errors[] = 'Please choose how pay is calculated.';
    }
    if (!ctype_digit($old['job_type_id'])) {
        $errors[] = 'Please choose a job type.';
    }
    if (!is_numeric($old['pay_rate']) || (float) $old['pay_rate'] <= 0) {
        $errors[] = 'Pay rate must be a positive number.';
    }
    if (!ctype_digit($old['vacancies']) || (int) $old['vacancies'] < 1) {
        $errors[] = 'Vacancies must be at least 1.';
    }
    if ($old['start_date'] !== '' && $old['end_date'] !== '' && $old['end_date'] < $old['start_date']) {
        $errors[] = 'End date cannot be before the start date.';
    }

    if (empty($errors)) {
        $openStatusId = $pdo->query("SELECT status_id FROM Job_Statuses WHERE status_name = 'open'")->fetchColumn();

        $insert = $pdo->prepare(
            'INSERT INTO Jobs (employer_id, company_id, category_id, area_id, salary_type_id, job_type_id,
                                status_id, title, description, requirements, pay_rate, vacancies,
                                experience_required, application_deadline, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $employerId,
            $company['company_id'] ?? null,
            (int) $old['category_id'],
            (int) $old['area_id'],
            (int) $old['salary_type_id'],
            (int) $old['job_type_id'],
            $openStatusId,
            $old['title'],
            $old['description'],
            $old['requirements'] !== '' ? $old['requirements'] : null,
            (float) $old['pay_rate'],
            (int) $old['vacancies'],
            $old['experience_required'] !== '' ? $old['experience_required'] : null,
            $old['application_deadline'] !== '' ? $old['application_deadline'] : null,
            $old['start_date'] !== '' ? $old['start_date'] : null,
            $old['end_date'] !== '' ? $old['end_date'] : null,
        ]);

        setFlash('success', 'Your job has been posted.');
        header('Location: /auren/employer/my_jobs.php');
        exit;
    }
}

$pageTitle = 'Post a Job';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'post_job'; require_once __DIR__ . '/../includes/sidebar_employer.php'; ?>
    <div class="auren-dashboard-content" style="max-width: 760px;">
        <h1 class="h3 fw-bold mb-1">Post a job</h1>
        <p class="text-muted mb-4">Describe the work, set your budget, and publish.</p>

        <?php if (!$company): ?>
            <div class="alert alert-warning">
                You haven't set up a company profile yet — this job will be posted under your
                personal name. <a href="/auren/employer/company_profile.php">Set one up</a> if you're hiring on behalf of a business.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/auren/employer/post_job.php" class="auren-card">
            <div class="mb-3">
                <label class="form-label fw-semibold">Job title</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($old['title']) ?>"
                    placeholder="e.g. Plumber Needed for 2-Day Apartment Fix" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($old['description']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Requirements <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="requirements" class="form-control" rows="3"
                    placeholder="e.g. 2+ years experience, own tools preferred"><?= htmlspecialchars($old['requirements']) ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Choose...</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['category_id'] ?>" <?= $old['category_id'] == $c['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Location (area)</label>
                    <select name="area_id" class="form-select" required>
                        <option value="">Choose...</option>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['area_id'] ?>" <?= $old['area_id'] == $a['area_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['area_name']) ?> (<?= htmlspecialchars($a['district_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Pay rate (Tk)</label>
                    <input type="number" step="0.01" min="0.01" name="pay_rate" class="form-control"
                        value="<?= htmlspecialchars($old['pay_rate']) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Paid as</label>
                    <select name="salary_type_id" class="form-select" required>
                        <option value="">Choose...</option>
                        <?php foreach ($salaryTypes as $s): ?>
                            <option value="<?= $s['salary_type_id'] ?>" <?= $old['salary_type_id'] == $s['salary_type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($s['type_name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Job type</label>
                    <select name="job_type_id" class="form-select" required>
                        <option value="">Choose...</option>
                        <?php foreach ($jobTypes as $j): ?>
                            <option value="<?= $j['job_type_id'] ?>" <?= $old['job_type_id'] == $j['job_type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($j['job_type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Vacancies</label>
                    <input type="number" min="1" name="vacancies" class="form-control"
                        value="<?= htmlspecialchars($old['vacancies']) ?>" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label fw-semibold">Experience required <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="experience_required" class="form-control"
                        value="<?= htmlspecialchars($old['experience_required']) ?>" placeholder="e.g. 2+ years preferred">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Application deadline <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" name="application_deadline" class="form-control" value="<?= htmlspecialchars($old['application_deadline']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Start date <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($old['start_date']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">End date <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($old['end_date']) ?>">
                </div>
            </div>

            <button type="submit" class="btn auren-btn-primary w-100 py-2 mt-2">Publish job</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
