<?php
/**
 * seeker/resume.php
 *
 * Create-or-update the logged-in seeker's ONE resume (the schema enforces
 * one resume per seeker via UNIQUE(seeker_id), so this page is deliberately
 * a single form that either inserts the first time or updates thereafter —
 * there is no "list of resumes").
 *
 * Skills are a many-to-many (Resume_Skills). On save we sync the join table
 * to exactly the set of checkboxes the seeker submitted: insert the new
 * ones, delete the removed ones. The whole save (resume row + skills) runs
 * in a single transaction so a half-saved resume can never happen.
 *
 * This is the page the apply flow (Phase 4) already links to for seekers who
 * don't yet have a resume — building it closes that gap.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('seeker');

$seekerId = currentUserId();

// Load existing resume (if any) so the form is pre-filled on edit.
$resumeStmt = $pdo->prepare('SELECT * FROM Resumes WHERE seeker_id = ?');
$resumeStmt->execute([$seekerId]);
$resume = $resumeStmt->fetch();

// All skills for the picker.
$allSkills = $pdo->query('SELECT skill_id, skill_name FROM Skills ORDER BY skill_name')->fetchAll();

// Which skills are already on this resume (for pre-checking boxes).
$selectedSkillIds = [];
if ($resume) {
    $selStmt = $pdo->prepare('SELECT skill_id FROM Resume_Skills WHERE resume_id = ?');
    $selStmt->execute([$resume['resume_id']]);
    $selectedSkillIds = array_map('intval', $selStmt->fetchAll(PDO::FETCH_COLUMN));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headline   = trim($_POST['headline'] ?? '');
    $summary    = trim($_POST['summary'] ?? '');
    $education  = trim($_POST['education'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $resumePath = trim($_POST['resume_path'] ?? '');
    // Submitted skill ids, coerced to ints and filtered to real skills.
    $postedSkillIds = array_map('intval', $_POST['skills'] ?? []);
    $validSkillIds = array_column($allSkills, 'skill_id');
    $postedSkillIds = array_values(array_intersect($postedSkillIds, array_map('intval', $validSkillIds)));

    // Keep the just-submitted selection so the form re-renders correctly on error.
    $selectedSkillIds = $postedSkillIds;

    // ---- Validation ----
    if ($headline === '' || strlen($headline) < 3) {
        $errors[] = 'Please add a short headline (at least 3 characters), e.g. "Reliable delivery rider".';
    }
    if (strlen($headline) > 150) {
        $errors[] = 'Headline must be 150 characters or fewer.';
    }
    if ($summary === '') {
        $errors[] = 'Please write a brief summary about yourself.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($resume) {
                // Update existing resume row.
                $upd = $pdo->prepare(
                    'UPDATE Resumes
                     SET headline = ?, summary = ?, education = ?, experience = ?, resume_path = ?
                     WHERE resume_id = ? AND seeker_id = ?'
                );
                $upd->execute([
                    $headline, $summary,
                    $education !== '' ? $education : null,
                    $experience !== '' ? $experience : null,
                    $resumePath !== '' ? $resumePath : null,
                    $resume['resume_id'], $seekerId,
                ]);
                $resumeId = (int) $resume['resume_id'];
            } else {
                // First-time insert.
                $ins = $pdo->prepare(
                    'INSERT INTO Resumes (seeker_id, headline, summary, education, experience, resume_path)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $seekerId, $headline, $summary,
                    $education !== '' ? $education : null,
                    $experience !== '' ? $experience : null,
                    $resumePath !== '' ? $resumePath : null,
                ]);
                $resumeId = (int) $pdo->lastInsertId();
            }

            // ---- Sync skills: add the new, remove the gone ----
            $currentStmt = $pdo->prepare('SELECT skill_id FROM Resume_Skills WHERE resume_id = ?');
            $currentStmt->execute([$resumeId]);
            $currentSkillIds = array_map('intval', $currentStmt->fetchAll(PDO::FETCH_COLUMN));

            $toAdd = array_diff($postedSkillIds, $currentSkillIds);
            $toRemove = array_diff($currentSkillIds, $postedSkillIds);

            if (!empty($toAdd)) {
                $addStmt = $pdo->prepare('INSERT INTO Resume_Skills (resume_id, skill_id) VALUES (?, ?)');
                foreach ($toAdd as $sid) {
                    $addStmt->execute([$resumeId, $sid]);
                }
            }
            if (!empty($toRemove)) {
                $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
                $delStmt = $pdo->prepare(
                    "DELETE FROM Resume_Skills WHERE resume_id = ? AND skill_id IN ($placeholders)"
                );
                $delStmt->execute(array_merge([$resumeId], array_values($toRemove)));
            }

            $pdo->commit();

            setFlash('success', $resume ? 'Your resume has been updated.' : 'Your resume has been created — you can now apply to jobs.');
            header('Location: /auren/seeker/resume.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while saving your resume. Please try again.';
        }
    }

    // On error, keep what the user typed so nothing is lost.
    $resume = array_merge($resume ?: [], [
        'headline' => $headline,
        'summary' => $summary,
        'education' => $education,
        'experience' => $experience,
        'resume_path' => $resumePath,
    ]);
}

$pageTitle = 'My Resume';
require_once __DIR__ . '/../includes/header.php';
renderFlash();

$val = function (string $key) use ($resume) {
    return htmlspecialchars($resume[$key] ?? '');
};
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'resume'; require_once __DIR__ . '/../includes/sidebar_seeker.php'; ?>
    <div class="auren-dashboard-content">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">My Resume</h1>
            <p class="text-muted mb-0">
                <?= $resume && !empty($resume['resume_id'])
                    ? 'Keep your resume up to date — it\'s sent with every application.'
                    : 'Create your resume once. It\'s required before you can apply to jobs.' ?>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/auren/seeker/resume.php" novalidate>
            <div class="auren-card mb-3">
                <h2 class="h6 fw-bold mb-3">Basics</h2>

                <div class="mb-3">
                    <label for="headline" class="form-label fw-semibold">Headline <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="headline" name="headline" maxlength="150"
                        value="<?= $val('headline') ?>" placeholder="e.g. Experienced waiter & event staff" required>
                    <div class="form-text">A one-line summary of who you are professionally.</div>
                </div>

                <div class="mb-0">
                    <label for="summary" class="form-label fw-semibold">Summary <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="summary" name="summary" rows="4"
                        placeholder="A short paragraph about your experience, strengths, and what work you're looking for." required><?= $val('summary') ?></textarea>
                </div>
            </div>

            <div class="auren-card mb-3">
                <h2 class="h6 fw-bold mb-3">Background</h2>

                <div class="mb-3">
                    <label for="education" class="form-label fw-semibold">Education <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control" id="education" name="education" rows="3"
                        placeholder="e.g. HSC, Dhaka College, 2022"><?= $val('education') ?></textarea>
                </div>

                <div class="mb-0">
                    <label for="experience" class="form-label fw-semibold">Experience <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control" id="experience" name="experience" rows="3"
                        placeholder="e.g. 2 years as delivery rider at FoodPanda; weekend event staff."><?= $val('experience') ?></textarea>
                </div>
            </div>

            <div class="auren-card mb-3">
                <h2 class="h6 fw-bold mb-1">Skills</h2>
                <p class="text-muted small mb-3">Select the skills that apply to you. Employers see these on your application.</p>

                <div class="row g-2">
                    <?php foreach ($allSkills as $skill): ?>
                        <?php $checked = in_array((int) $skill['skill_id'], $selectedSkillIds, true); ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="skills[]"
                                    value="<?= (int) $skill['skill_id'] ?>" id="skill<?= (int) $skill['skill_id'] ?>"
                                    <?= $checked ? 'checked' : '' ?>>
                                <label class="form-check-label" for="skill<?= (int) $skill['skill_id'] ?>">
                                    <?= htmlspecialchars($skill['skill_name']) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="auren-card mb-3">
                <h2 class="h6 fw-bold mb-1">Attachment <span class="text-muted fw-normal small">(optional)</span></h2>
                <p class="text-muted small mb-3">If you host your CV online (Google Drive, Dropbox, etc.), paste the link here.</p>
                <label for="resume_path" class="form-label fw-semibold">Resume link</label>
                <input type="url" class="form-control" id="resume_path" name="resume_path"
                    value="<?= $val('resume_path') ?>" placeholder="https://...">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn auren-btn-primary px-4">
                    <?= $resume && !empty($resume['resume_id']) ? 'Save changes' : 'Create resume' ?>
                </button>
                <a href="/auren/seeker/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
