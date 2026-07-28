<?php
/**
 * employer/company_profile.php
 *
 * Create-or-update form for this employer's Company row. Handles both
 * cases in one page: if no Companies row exists yet for this employer,
 * submitting the form INSERTs one; if it already exists, submitting
 * UPDATEs it. This matches the confirmed 1 : 0..1 relationship (one
 * employer, at most one company profile).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('employer');

$employerId = currentUserId();

$companyStmt = $pdo->prepare('SELECT * FROM Companies WHERE employer_id = ?');
$companyStmt->execute([$employerId]);
$company = $companyStmt->fetch();

$industries = $pdo->query('SELECT industry_id, industry_name FROM Industries ORDER BY industry_name')->fetchAll();
$sizes = $pdo->query('SELECT size_id, size_label FROM Company_Sizes ORDER BY size_id')->fetchAll();

$errors = [];
$old = [
    'company_name' => $company['company_name'] ?? '',
    'description' => $company['description'] ?? '',
    'website' => $company['website'] ?? '',
    'address' => $company['address'] ?? '',
    'industry_id' => $company['industry_id'] ?? '',
    'size_id' => $company['size_id'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['company_name', 'description', 'website', 'address', 'industry_id', 'size_id'] as $key) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    if (strlen($old['company_name']) < 2) {
        $errors[] = 'Please enter your company name.';
    }
    if ($old['website'] !== '' && !filter_var($old['website'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Please enter a valid website URL (e.g. https://example.com).';
    }

    if (empty($errors)) {
        $industryId = $old['industry_id'] !== '' ? (int) $old['industry_id'] : null;
        $sizeId = $old['size_id'] !== '' ? (int) $old['size_id'] : null;

        if ($company) {
            $update = $pdo->prepare(
                'UPDATE Companies SET company_name = ?, description = ?, website = ?, address = ?,
                    industry_id = ?, size_id = ? WHERE employer_id = ?'
            );
            $update->execute([
                $old['company_name'], $old['description'] ?: null, $old['website'] ?: null,
                $old['address'] ?: null, $industryId, $sizeId, $employerId,
            ]);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO Companies (employer_id, company_name, description, website, address, industry_id, size_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $employerId, $old['company_name'], $old['description'] ?: null, $old['website'] ?: null,
                $old['address'] ?: null, $industryId, $sizeId,
            ]);
        }

        setFlash('success', 'Company profile saved.');
        header('Location: /auren/employer/company_profile.php');
        exit;
    }
}

$pageTitle = 'Company Profile';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'company_profile'; require_once __DIR__ . '/../includes/sidebar_employer.php'; ?>
    <div class="auren-dashboard-content" style="max-width: 700px;">
        <h1 class="h3 fw-bold mb-1">Company profile</h1>
        <p class="text-muted mb-4">
            <?= $company ? 'Update your company details below.' : "Set this up so your jobs show your company name, not just yours." ?>
        </p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="/auren/employer/company_profile.php" class="auren-card">
            <div class="mb-3">
                <label class="form-label fw-semibold">Company name</label>
                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($old['company_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($old['description']) ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Industry</label>
                    <select name="industry_id" class="form-select">
                        <option value="">Not specified</option>
                        <?php foreach ($industries as $i): ?>
                            <option value="<?= $i['industry_id'] ?>" <?= $old['industry_id'] == $i['industry_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($i['industry_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Company size</label>
                    <select name="size_id" class="form-select">
                        <option value="">Not specified</option>
                        <?php foreach ($sizes as $s): ?>
                            <option value="<?= $s['size_id'] ?>" <?= $old['size_id'] == $s['size_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['size_label']) ?> employees
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Website <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($old['website']) ?>" placeholder="https://example.com">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Address <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($old['address']) ?>">
            </div>

            <button type="submit" class="btn auren-btn-primary w-100 py-2 mt-2">
                <?= $company ? 'Save changes' : 'Create company profile' ?>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
