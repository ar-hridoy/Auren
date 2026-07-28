<?php
/**
 * auth/login.php
 *
 * Verifies email + password against Users.password_hash (password_verify,
 * matching the bcrypt hash from registration), then sets session variables
 * and hands off to redirect_by_role.php.
 *
 * Deliberately vague error message ("Invalid email or password") rather
 * than "no account with that email" / "wrong password" separately — this
 * is standard practice so a login form can't be used to enumerate which
 * emails are registered.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_tokens.php';
require_once __DIR__ . '/../includes/flash.php';

if (isLoggedIn()) {
    header('Location: /auren/includes/redirect_by_role.php');
    exit;
}

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($oldEmail === '' || $password === '') {
        $errors[] = 'Please enter both your email and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, email, password_hash, role_id, two_factor_enabled
             FROM Users WHERE email = ?'
        );
        $stmt->execute([$oldEmail]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Look up the role name for the session (role_id -> role_name)
            $roleStmt = $pdo->prepare('SELECT role_name FROM Roles WHERE role_id = ?');
            $roleStmt->execute([$user['role_id']]);
            $roleName = $roleStmt->fetchColumn();

            if (!empty($user['two_factor_enabled'])) {
                // Password is correct, but require a second factor before a real
                // session is created. Hold identity in a short-lived pending key.
                $_SESSION['pending_2fa'] = [
                    'user_id'   => (int) $user['user_id'],
                    'full_name' => $user['full_name'],
                    'email'     => $user['email'],
                    'role'      => $roleName,
                ];
                $code = createTwoFactorCode($pdo, (int) $user['user_id']);
                $body = "Your Auren verification code is: $code\n\n"
                      . "It expires in " . TWOFA_TTL_MINUTES . " minutes.";
                if (deliverAuthMessage($user['email'], 'Your Auren verification code', $body)) {
                    // Dev mode: stash the code so the verify page can reveal it.
                    $_SESSION['pending_2fa']['demo_code'] = $code;
                }
                header('Location: /auren/auth/verify_2fa.php');
                exit;
            }

            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $roleName;

            header('Location: /auren/includes/redirect_by_role.php');
            exit;
        }

        $errors[] = 'Invalid email or password.';
    }
}

$pageTitle = 'Sign in';
$panelSide = 'right';
$panelStyle = 'center';
$panelHeading = 'The fastest way to fill shifts and find flexible work.';
$panelStat = 'Post a job in minutes. Apply in one step.';
require_once __DIR__ . '/../includes/auth_header.php';

// Show any pending flash (e.g. "Account created! Please log in") inside the form column.
if (!empty($_SESSION['flash'])) {
    $flashType = htmlspecialchars($_SESSION['flash']['type']);
    $flashMessage = htmlspecialchars($_SESSION['flash']['message']);
    unset($_SESSION['flash']);
    echo '<div class="alert alert-' . $flashType . '">' . $flashMessage . '</div>';
}
?>

<a href="/auren/index.php" class="auren-auth-back"><i class="bi bi-arrow-left"></i> Back</a>
<h1 class="auren-auth-title">Welcome back</h1>
<p class="auren-auth-subtitle">Sign in to manage your jobs and applications.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="/auren/auth/login.php" novalidate>
    <div class="mb-3">
        <div class="auren-auth-label">Email</div>
        <input type="email" class="auren-auth-input" id="email" name="email"
            value="<?= htmlspecialchars($oldEmail) ?>" placeholder="you@email.com" required autofocus>
    </div>
    <div class="mb-4">
        <div class="auren-auth-label d-flex justify-content-between align-items-center">
            <span>Password</span>
            <a href="/auren/auth/forgot_password.php" class="auren-auth-forgot">Forgot password?</a>
        </div>
        <input type="password" class="auren-auth-input" id="password" name="password" required>
    </div>
    <button type="submit" class="auren-auth-submit">Sign in</button>
</form>

<p class="auren-auth-alt">New to Auren? <a href="/auren/auth/register.php">Create an account</a></p>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
