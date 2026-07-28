<?php
/**
 * auth/reset_password.php?token=RAW
 *
 * Step 2 of password recovery. Validates the single-use token, then lets the
 * user set a new password. On success the token is consumed (can't be reused)
 * and any existing login session is left untouched — the user signs in fresh.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_tokens.php';
require_once __DIR__ . '/../includes/flash.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$errors = [];
$userId = $token !== '' ? verifyPasswordResetToken($pdo, $token) : null;
$tokenValid = $userId !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE Users SET password_hash = ? WHERE user_id = ?')->execute([$hash, $userId]);
        consumePasswordResetToken($pdo, $token);
        setFlash('success', 'Your password has been reset. Please sign in with your new password.');
        header('Location: /auren/auth/login.php');
        exit;
    }
}

$pageTitle = 'Reset password';
$panelSide = 'right';
$panelStyle = 'center';
$panelHeading = 'Choose a new password.';
$panelStat = 'Make it strong — at least 8 characters.';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<a href="/auren/auth/login.php" class="auren-auth-back"><i class="bi bi-arrow-left"></i> Back to sign in</a>
<h1 class="auren-auth-title">Reset password</h1>

<?php if (!$tokenValid): ?>
    <div class="alert alert-danger">
        This reset link is invalid or has expired. Reset links are valid for
        <?= RESET_TTL_MINUTES ?> minutes and can only be used once.
    </div>
    <p class="auren-auth-alt"><a href="/auren/auth/forgot_password.php">Request a new link</a></p>
<?php else: ?>
    <p class="auren-auth-subtitle">Enter a new password for your account.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/auren/auth/reset_password.php" novalidate>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="mb-3">
            <div class="auren-auth-label">New password</div>
            <input type="password" class="auren-auth-input" name="new_password" required minlength="8" autofocus>
        </div>
        <div class="mb-4">
            <div class="auren-auth-label">Confirm new password</div>
            <input type="password" class="auren-auth-input" name="confirm_password" required minlength="8">
        </div>
        <button type="submit" class="auren-auth-submit">Reset password</button>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
