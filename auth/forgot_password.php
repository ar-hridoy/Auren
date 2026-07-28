<?php
/**
 * auth/forgot_password.php
 *
 * Step 1 of password recovery: the user enters their email. If an account
 * exists, a single-use reset token is created and "delivered".
 *
 * The confirmation message is deliberately the SAME whether or not the email
 * is registered, so this form can't be used to discover which emails have
 * accounts (email enumeration). In dev mode the reset link is additionally
 * shown on screen so the flow is testable without a mail server.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_tokens.php';

if (isLoggedIn()) {
    header('Location: /auren/includes/redirect_by_role.php');
    exit;
}

$submitted = false;
$demoLink = null;
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    $oldEmail = trim($_POST['email'] ?? '');

    if ($oldEmail !== '') {
        $stmt = $pdo->prepare('SELECT user_id, full_name FROM Users WHERE email = ? AND deleted_at IS NULL');
        $stmt->execute([$oldEmail]);
        $user = $stmt->fetch();

        if ($user) {
            $raw = createPasswordResetToken($pdo, (int) $user['user_id']);
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $link = 'http://' . $host . '/auren/auth/reset_password.php?token=' . $raw;

            $body = "Hi {$user['full_name']},\n\n"
                  . "We received a request to reset your Auren password. "
                  . "Use the link below within " . RESET_TTL_MINUTES . " minutes:\n\n"
                  . $link . "\n\n"
                  . "If you didn't request this, you can ignore this message.";

            $demoLink = deliverAuthMessage($oldEmail, 'Reset your Auren password', $body) ? $link : null;
        }
        // If no user, we still fall through to the same generic confirmation.
    }
}

$pageTitle = 'Forgot password';
$panelSide = 'right';
$panelStyle = 'center';
$panelHeading = 'Locked out? It happens.';
$panelStat = 'Reset your password and get back to work in a minute.';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<a href="/auren/auth/login.php" class="auren-auth-back"><i class="bi bi-arrow-left"></i> Back to sign in</a>
<h1 class="auren-auth-title">Forgot your password?</h1>
<p class="auren-auth-subtitle">Enter your email and we'll send you a link to reset it.</p>

<?php if ($submitted): ?>
    <div class="alert alert-success">
        If an account exists for <strong><?= htmlspecialchars($oldEmail) ?></strong>,
        a password reset link has been sent. Please check your email.
    </div>

    <?php if ($demoLink !== null): ?>
        <div class="alert alert-info" style="font-size:0.9rem;">
            <strong><i class="bi bi-tools"></i> Demo mode:</strong>
            email isn't configured locally, so here is the reset link:
            <div class="mt-2">
                <a href="<?= htmlspecialchars($demoLink) ?>" style="word-break:break-all;"><?= htmlspecialchars($demoLink) ?></a>
            </div>
        </div>
    <?php endif; ?>

    <p class="auren-auth-alt"><a href="/auren/auth/login.php">Return to sign in</a></p>
<?php else: ?>
    <form method="POST" action="/auren/auth/forgot_password.php" novalidate>
        <div class="mb-4">
            <div class="auren-auth-label">Email</div>
            <input type="email" class="auren-auth-input" name="email"
                value="<?= htmlspecialchars($oldEmail) ?>" placeholder="you@email.com" required autofocus>
        </div>
        <button type="submit" class="auren-auth-submit">Send reset link</button>
    </form>

    <p class="auren-auth-alt">Remembered it? <a href="/auren/auth/login.php">Sign in</a></p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
