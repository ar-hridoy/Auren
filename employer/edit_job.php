<?php
/**
 * employer/edit_job.php
 *
 * Loads a job by id, but ONLY if it belongs to the logged-in employer —
 * this ownership check is what actually enforces "one job belongs to
 * exactly one employer" (R6) at the application layer; the database
 * constraint alone doesn't stop employer A from guessing employer B's
 * job_id in the URL.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('employer');

$employerId = currentUserId();
$jobId = (int) ($_GET['id'] ?? $_POST['job_id'] ?? 0);

$jobStmt = $pdo->prepare('SELECT * FROM Jobs WHERE job_id = ? AND employer_id = ?');
$jobStmt->execute([$jobId, $employerId]);
$job = $jobStmt->fetch();

if (!$job) {
    setFlash('danger', 'Job not found, or you do not have permission to edit it.');
    header('Location: /auren/employer/my_jobs.php');
    exit;
}

$categories = $pdo->query('SELECT category_id, category_name FROM Categories ORDER BY category_name')->fetchAll();
$salaryTypes = $pdo->query('SELECT salary_type_id, type_name FROM Salary_Types ORDER BY salary_type_id')->fetchAll();
$jobTypes = $pdo->query('SELECT job_type_id, job_type_name FROM Job_Types ORDER BY job_type_name')->fetchAll();
$statuses = $pdo->query('SELECT status_id, status_name FROM Job_Statuses ORDER BY status_id')->fetchAll();
$areas = $pdo->query(
    'SELECT a.area_id, a.area_name, d.district_name
     FROM Areas a JOIN Districts d ON a.district_id = d.district_id
     ORDER BY d.district_name, a.area_name'
)->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $areaId = $_POST['area_id'] ?? '';
    $salaryTypeId = $_POST['salary_type_id'] ?? '';
    $jobTypeId = $_POST['job_type_id'] ?? '';
    $statusId = $_POST['status_id'] ?? '';
    $payRate = $_POST['pay_rate'] ?? '';
    $vacancies = $_POST['vacancies'] ?? '';
    $experienceRequired = trim($_POST['experience_required'] ?? '');
    $applicationDeadline = $_POST['application_deadline'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    if (strlen($title) < 5) $errors[] = 'Job title must be at least 5 characters.';
    if (strlen($description) < 20) $errors[] = 'Please provide a more detailed description.';
    if (!is_numeric($payRate) || (float) $payRate <= 0) $errors[] = 'Pay rate must be a positive number.';
    if (!ctype_digit((string) $vacancies) || (int) $vacancies < 1) $errors[] = 'Vacancies must be at least 1.';
    if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) $errors[] = 'End date cannot be before the start date.';

    if (empty($errors)) {
        $update = $pdo->prepare(
            'UPDATE Jobs SET
                title = ?, description = ?, requirements = ?, category_id = ?, area_id = ?,
                salary_type_id = ?, job_type_id = ?, status_id = ?, pay_rate = ?, vacancies = ?,
                experience_required = ?, application_deadline = ?, start_date = ?, end_date = ?
             WHERE job_id = ? AND employer_id = ?'
        );
        $update->execute([
            $title, $description, $requirements !== '' ? $requirements : null,
            (int) $categoryId, (int) $areaId, (int) $salaryTypeId, (int) $jobTypeId, (int) $statusId,
            (float) $payRate, (int) $vacancies,
            $experienceRequired !== '' ? $experienceRequired : null,
            $applicationDeadline !== '' ? $applicationDeadline : null,
            $startDate !== '' ? $startDate : null,
            $endDate !== '' ? $endDate : null,
            $jobId, $employerId,
        ]);

        setFlash('success', 'Job updated successfully.');
        header('Location: /auren/employer/my_jobs.php');
        exit;
    }

    // Keep the submitted values on screen if validation failed
    $job = array_merge($job, [
        'title' => $title, 'description' => $description, 'requirements' => $requirements,
        'category_id' => $categoryId, 'area_id' => $areaId, 'salary_type_id' => $salaryTypeId,
        'job_type_id' => $jobTypeId, 'status_id' => $statusId, 'pay_rate' => $payRate,
        'vacancies' => $vacancies, 'experience_required' => $experienceRequired,
        'application_deadline' => $applicationDeadline, 'start_date' => $startDate, 'end_date' => $endDate,
    ]);
}

$pageTitle = 'Edit Job';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'my_jobs'; require_once __DIR__ . '/../includes/sidebar_employer.php'; ?>
    <div class="auren-dashboard-content" style="max-width: 760px;">
        <h1 class="h3 fw-bold mb-1">Edit job</h1>
        <p class="text-muted mb-4"><?= htmlspecialchars($job['title']) ?></p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/auren/employer/edit_job.php?id=<?= $jobId ?>" class="auren-card">
            <input type="hidden" name="job_id" value="<?= $jobId ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold">Job title</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($job['title']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($job['description']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Requirements</label>
                <textarea name="requirements" class="form-control" rows="3"><?= htmlspecialchars($job['requirements'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category_id" class="form-select" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['category_id'] ?>" <?= $job['category_id'] == $c['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Location (area)</label>
                    <select name="area_id" class="form-select" required>
                        <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['area_id'] ?>" <?= $job['area_id'] == $a['area_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['area_name']) ?> (<?= htmlspecialchars($a['district_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Pay rate (Tk)</label>
                    <input type="number" step="0.01" min="0.01" name="pay_rate" class="form-control" value="<?= htmlspecialchars($job['pay_rate']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Paid as</label>
                    <select name="salary_type_id" class="form-select" required>
                        <?php foreach ($salaryTypes as $s): ?>
                            <option value="<?= $s['salary_type_id'] ?>" <?= $job['salary_type_id'] == $s['salary_type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($s['type_name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Job type</label>
                    <select name="job_type_id" class="form-select" required>
                        <?php foreach ($jobTypes as $j): ?>
                            <option value="<?= $j['job_type_id'] ?>" <?= $job['job_type_id'] == $j['job_type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($j['job_type_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status_id" class="form-select" required>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s['status_id'] ?>" <?= $job['status_id'] == $s['status_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($s['status_name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Vacancies</label>
                    <input type="number" min="1" name="vacancies" class="form-control" value="<?= htmlspecialchars($job['vacancies']) ?>" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label fw-semibold">Experience required</label>
                    <input type="text" name="experience_required" class="form-control" value="<?= htmlspecialchars($job['experience_required'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Application deadline</label>
                    <input type="date" name="application_deadline" class="form-control" value="<?= htmlspecialchars($job['application_deadline'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Start date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($job['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">End date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($job['end_date'] ?? '') ?>">
                </div>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn auren-btn-primary flex-grow-1 py-2">Save changes</button>
                <a href="/auren/employer/my_jobs.php" class="btn btn-outline-secondary py-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
