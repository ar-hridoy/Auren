<?php
/**
 * auth/verify_2fa.php
 *
 * Second step of a two-step login. Reached only after a correct password when
 * the account has two_factor_enabled = TRUE. The user's identity is held in a
 * short-lived "pending 2FA" session key (NOT a full login) until they enter a
 * valid code, at which point the real session is established.
 *
 * A "Resend code" action issues a fresh code (old ones are invalidated).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_tokens.php';

// Must have a pending 2FA challenge, otherwise there is nothing to verify.
$pending = $_SESSION['pending_2fa'] ?? null;
if (!$pending || empty($pending['user_id'])) {
    header('Location: /auren/auth/login.php');
    exit;
}
$pendingUserId = (int) $pending['user_id'];

$errors = [];
$demoCode = null;
$notice = null;

// If login stashed a demo code (dev mode), reveal it on first arrival.
if (!empty($pending['demo_code'])) {
    $demoCode = $pending['demo_code'];
}

// Resend a fresh code.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    $code = createTwoFactorCode($pdo, $pendingUserId);
    $body = "Your Auren verification code is: $code\n\n"
          . "It expires in " . TWOFA_TTL_MINUTES . " minutes.";
    $delivered = deliverAuthMessage($pending['email'], 'Your Auren verification code', $body);
    if ($delivered) {
        $demoCode = $code;
        $_SESSION['pending_2fa']['demo_code'] = $code;
    }
    $notice = 'A new code has been sent.';
}

// Verify a submitted code.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $code = trim($_POST['code'] ?? '');
    if ($code === '') {
        $errors[] = 'Please enter the 6-digit code.';
    } elseif (verifyTwoFactorCode($pdo, $pendingUserId, $code)) {
        // Success — promote the pending challenge to a real session.
        $_SESSION['user_id'] = $pendingUserId;
        $_SESSION['full_name'] = $pending['full_name'];
        $_SESSION['role'] = $pending['role'];
        unset($_SESSION['pending_2fa']);
        header('Location: /auren/includes/redirect_by_role.php');
        exit;
    } else {
        $errors[] = 'That code is incorrect or has expired.';
    }
}

$pageTitle = 'Verify sign in';
$panelSide = 'right';
$panelStyle = 'center';
$panelHeading = 'One more step to keep your account safe.';
$panelStat = 'Two-step verification blocks logins even if a password leaks.';
require_once __DIR__ . '/../includes/auth_header.php';
?>

<a href="/auren/auth/login.php" class="auren-auth-back"><i class="bi bi-arrow-left"></i> Cancel</a>
<h1 class="auren-auth-title">Enter your code</h1>
<p class="auren-auth-subtitle">
    We sent a 6-digit verification code to
    <strong><?= htmlspecialchars($pending['email']) ?></strong>.
</p>

<?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($demoCode !== null): ?>
    <div class="alert alert-info" style="font-size:0.9rem;">
        <strong><i class="bi bi-tools"></i> Demo mode:</strong>
        email isn't configured locally, so your code is
        <strong style="letter-spacing:2px;"><?= htmlspecialchars($demoCode) ?></strong>.
    </div>
<?php endif; ?>

<form method="POST" action="/auren/auth/verify_2fa.php" novalidate>
    <input type="hidden" name="action" value="verify">
    <div class="mb-4">
        <div class="auren-auth-label">Verification code</div>
        <input type="text" class="auren-auth-input" name="code" inputmode="numeric" pattern="[0-9]*"
            maxlength="6" placeholder="123456" required autofocus
            style="letter-spacing:6px;font-size:1.3rem;text-align:center;">
    </div>
    <button type="submit" class="auren-auth-submit">Verify &amp; sign in</button>
</form>

<form method="POST" action="/auren/auth/verify_2fa.php" class="mt-2">
    <input type="hidden" name="action" value="resend">
    <p class="auren-auth-alt">
        Didn't get it? <button type="submit" class="auren-auth-linkbtn">Resend code</button>
    </p>
</form>

<?php require_once __DIR__ . '/../includes/auth_footer.php'; ?>
