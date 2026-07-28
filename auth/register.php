<?php
/**
 * auth/register.php
 *
 * Single registration form for both roles (Requirements Spec 4.1: "Users
 * must select a role during registration"). Employer registrations also
 * capture employer_type (individual/company) since that's required by
 * the Employers table (Improvement 2's supertype/subtype design) — a
 * Company profile itself is a separate, later step (Phase 3), not part
 * of registration.
 *
 * On success: inserts into Users, then into Employers or Seekers, inside
 * a single transaction — if either insert fails, neither is kept.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';

// Already logged in? Don't show the register form, just send them onward.
if (isLoggedIn()) {
    header('Location: /auren/includes/redirect_by_role.php');
    exit;
}

$errors = [];
$old = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'role' => $_GET['role'] ?? 'seeker',
    'employer_type' => 'individual',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['role'] = $_POST['role'] ?? 'seeker';
    $old['employer_type'] = $_POST['employer_type'] ?? 'individual';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // ---- Validation ----
    if ($old['full_name'] === '' || strlen($old['full_name']) < 2) {
        $errors[] = 'Please enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    if (!in_array($old['role'], ['employer', 'seeker'], true)) {
        $errors[] = 'Please choose whether you are an Employer or a Job Seeker.';
    }
    if ($old['role'] === 'employer' && !in_array($old['employer_type'], ['individual', 'company'], true)) {
        $errors[] = 'Please choose Individual or Company.';
    }

    // Uniqueness check (friendlier than waiting for the DB's UNIQUE constraint to fail)
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT user_id FROM Users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists. Try logging in instead.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Look up role_id by name rather than hardcoding IDs, so this
            // code keeps working even if Roles seed data ever changes order.
            $roleStmt = $pdo->prepare('SELECT role_id FROM Roles WHERE role_name = ?');
            $roleStmt->execute([$old['role']]);
            $roleId = $roleStmt->fetchColumn();
            if (!$roleId) {
                throw new RuntimeException('Role not found in database.');
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $insertUser = $pdo->prepare(
                'INSERT INTO Users (full_name, email, password_hash, role_id, phone, is_verified)
                 VALUES (?, ?, ?, ?, ?, FALSE)'
            );
            $insertUser->execute([
                $old['full_name'],
                $old['email'],
                $passwordHash,
                $roleId,
                $old['phone'] !== '' ? $old['phone'] : null,
            ]);
            $newUserId = (int) $pdo->lastInsertId();

            if ($old['role'] === 'employer') {
                $typeStmt = $pdo->prepare('SELECT employer_type_id FROM Employer_Types WHERE type_name = ?');
                $typeStmt->execute([$old['employer_type']]);
                $employerTypeId = $typeStmt->fetchColumn();

                $insertEmployer = $pdo->prepare(
                    'INSERT INTO Employers (user_id, employer_type_id) VALUES (?, ?)'
                );
                $insertEmployer->execute([$newUserId, $employerTypeId]);
            } else {
                $insertSeeker = $pdo->prepare('INSERT INTO Seekers (user_id) VALUES (?)');
                $insertSeeker->execute([$newUserId]);
            }

            $pdo->commit();

            setFlash('success', 'Account created! Please log in to continue.');
            header('Location: /auren/auth/login.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        }
    }
}

$pageTitle = 'Create your account';
$panelSide = 'left';
$panelStyle = 'quote';
$panelQuote = '"I posted a shift in the morning and had the right person on the floor by evening."';
$panelAttribution = 'A restaurant owner in Dhaka';
$panelStat = 'Built for temporary hiring across Dhaka and nearby areas.';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<a href="/auren/index.php" class="auren-auth-back"><i class="bi bi-arrow-left"></i> Back</a>
<h1 class="auren-auth-title">Create your account</h1>
<p class="auren-auth-subtitle">Join Auren in under 2 minutes.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="/auren/auth/register.php" novalidate>
    <!-- "I want to" role selector as two cards -->
    <div class="auren-auth-eyebrow">I want to</div>
    <div class="auren-role-cards">
        <div style="flex:1 1 50%; position:relative;">
            <input type="radio" class="auren-role-input" name="role" id="roleSeeker" value="seeker"
                <?= $old['role'] === 'seeker' ? 'checked' : '' ?> onchange="toggleEmployerFields()">
            <label class="auren-role-card" for="roleSeeker">
                <div class="auren-role-card-title">Find work</div>
                <div class="auren-role-card-sub">Browse and apply to jobs</div>
            </label>
        </div>
        <div style="flex:1 1 50%; position:relative;">
            <input type="radio" class="auren-role-input" name="role" id="roleEmployer" value="employer"
                <?= $old['role'] === 'employer' ? 'checked' : '' ?> onchange="toggleEmployerFields()">
            <label class="auren-role-card" for="roleEmployer">
                <div class="auren-role-card-title">Hire talent</div>
                <div class="auren-role-card-sub">Post a job, review applicants</div>
            </label>
        </div>
    </div>

    <!-- Employer-only: individual vs company -->
    <div class="mb-3" id="employerTypeField" style="display:none;">
        <div class="auren-auth-label">You are hiring as</div>
        <div class="auren-role-cards" style="margin-bottom:0.5rem;">
            <div style="flex:1 1 50%; position:relative;">
                <input type="radio" class="auren-role-input" name="employer_type" id="typeIndividual" value="individual"
                    <?= $old['employer_type'] === 'individual' ? 'checked' : '' ?>>
                <label class="auren-role-card" for="typeIndividual">
                    <div class="auren-role-card-title">An individual</div>
                </label>
            </div>
            <div style="flex:1 1 50%; position:relative;">
                <input type="radio" class="auren-role-input" name="employer_type" id="typeCompany" value="company"
                    <?= $old['employer_type'] === 'company' ? 'checked' : '' ?>>
                <label class="auren-role-card" for="typeCompany">
                    <div class="auren-role-card-title">A company</div>
                </label>
            </div>
        </div>
        <div class="form-text mb-3">You can add full company details later from your dashboard.</div>
    </div>

    <div class="mb-3">
        <div class="auren-auth-label">Full name</div>
        <input type="text" class="auren-auth-input" id="full_name" name="full_name"
            value="<?= htmlspecialchars($old['full_name']) ?>" placeholder="e.g. Alex Rahman" required>
    </div>

    <div class="mb-3">
        <div class="auren-auth-label">Email</div>
        <input type="email" class="auren-auth-input" id="email" name="email"
            value="<?= htmlspecialchars($old['email']) ?>" placeholder="you@email.com" required>
    </div>

    <div class="mb-3">
        <div class="auren-auth-label">Phone <span class="text-muted fw-normal">(optional)</span></div>
        <input type="text" class="auren-auth-input" id="phone" name="phone"
            value="<?= htmlspecialchars($old['phone']) ?>" placeholder="01XXXXXXXXX">
    </div>

    <div class="auren-auth-row2 mb-4">
        <div>
            <div class="auren-auth-label">Password</div>
            <input type="password" class="auren-auth-input" id="password" name="password" required minlength="8">
        </div>
        <div>
            <div class="auren-auth-label">Confirm</div>
            <input type="password" class="auren-auth-input" id="confirm_password" name="confirm_password" required minlength="8">
        </div>
    </div>

    <button type="submit" class="auren-auth-submit">Create account</button>

    <p class="auren-auth-legal">By signing up you agree to our Terms and Privacy Policy.</p>
</form>

<p class="auren-auth-alt">Already have an account? <a href="/auren/auth/login.php">Sign in</a></p>

<script>
    function toggleEmployerFields() {
        const isEmployer = document.getElementById('roleEmployer').checked;
        document.getElementById('employerTypeField').style.display = isEmployer ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', toggleEmployerFields);
</script>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
