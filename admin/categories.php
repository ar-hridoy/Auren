<?php
/**
 * admin/categories.php
 * Lets an admin extend two of the lookup tables that drive the whole app:
 * Categories (used when posting jobs) and Skills (used on resumes). Because
 * we deliberately model these as lookup tables rather than ENUMs, adding a
 * new value is a simple INSERT here — no schema change — which is exactly
 * the extensibility that design choice was meant to buy.
 * Both names are UNIQUE in the schema, so we check first for a friendly
 * message and let the constraint be the final guard.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        setFlash('danger', 'Please enter a name.');
    } elseif ($type === 'category') {
        $exists = $pdo->prepare('SELECT 1 FROM Categories WHERE category_name = ?');
        $exists->execute([$name]);
        if ($exists->fetchColumn()) {
            setFlash('info', 'That category already exists.');
        } else {
            $pdo->prepare('INSERT INTO Categories (category_name) VALUES (?)')->execute([$name]);
            setFlash('success', 'Category added.');
        }
    } elseif ($type === 'skill') {
        $exists = $pdo->prepare('SELECT 1 FROM Skills WHERE skill_name = ?');
        $exists->execute([$name]);
        if ($exists->fetchColumn()) {
            setFlash('info', 'That skill already exists.');
        } else {
            $pdo->prepare('INSERT INTO Skills (skill_name) VALUES (?)')->execute([$name]);
            setFlash('success', 'Skill added.');
        }
    } else {
        setFlash('danger', 'Unknown item type.');
    }

    header('Location: /auren/admin/categories.php');
    exit;
}

// Each category / skill with how many times it's in use (context for the admin).
$categories = $pdo->query(
    'SELECT c.category_id, c.category_name,
            (SELECT COUNT(*) FROM Jobs j WHERE j.category_id = c.category_id AND j.deleted_at IS NULL) AS job_count
     FROM Categories c ORDER BY c.category_name'
)->fetchAll();

$skills = $pdo->query(
    'SELECT s.skill_id, s.skill_name,
            (SELECT COUNT(*) FROM Resume_Skills rs WHERE rs.skill_id = s.skill_id) AS use_count
     FROM Skills s ORDER BY s.skill_name'
)->fetchAll();

$pageTitle = 'Categories & Skills';
require_once __DIR__ . '/../includes/header.php';
renderFlash();
?>

<div class="auren-dashboard-wrap">
    <?php $activePage = 'categories'; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>
    <div class="auren-dashboard-content">
        <div class="mb-4">
            <h1 class="h3 fw-bold mb-1">Categories &amp; Skills</h1>
            <p class="text-muted mb-0">Manage the lookup values that power job posting and resumes.</p>
        </div>

        <div class="row g-3">
            <!-- Categories -->
            <div class="col-lg-6">
                <div class="auren-card h-100">
                    <h2 class="h6 fw-bold mb-3">Job Categories <span class="text-muted fw-normal">(<?= count($categories) ?>)</span></h2>

                    <form method="POST" action="/auren/admin/categories.php" class="d-flex gap-2 mb-3">
                        <input type="hidden" name="type" value="category">
                        <input type="text" name="name" class="form-control" placeholder="New category name" required>
                        <button type="submit" class="btn auren-btn-primary flex-shrink-0">Add</button>
                    </form>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-muted small"><th>Category</th><th class="text-end">Jobs using it</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['category_name']) ?></td>
                                        <td class="text-end text-muted"><?= (int) $c['job_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Skills -->
            <div class="col-lg-6">
                <div class="auren-card h-100">
                    <h2 class="h6 fw-bold mb-3">Skills <span class="text-muted fw-normal">(<?= count($skills) ?>)</span></h2>

                    <form method="POST" action="/auren/admin/categories.php" class="d-flex gap-2 mb-3">
                        <input type="hidden" name="type" value="skill">
                        <input type="text" name="name" class="form-control" placeholder="New skill name" required>
                        <button type="submit" class="btn auren-btn-primary flex-shrink-0">Add</button>
                    </form>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="text-muted small"><th>Skill</th><th class="text-end">On resumes</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skills as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['skill_name']) ?></td>
                                        <td class="text-end text-muted"><?= (int) $s['use_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
