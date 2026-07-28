<?php
/**
 * includes/auth_tokens.php
 *
 * Shared helpers for the two account-security features:
 *   - password reset tokens (Password_Resets)
 *   - two-step verification login codes (Two_Factor_Codes)
 *
 * DELIVERY NOTE
 * -------------
 * Reset links and 2FA codes are sent by email via PHPMailer/SMTP. Configure
 * your SMTP credentials in config/mail.php and set MAIL_ENABLED = true there.
 *
 * If mail is disabled, or an SMTP send fails (e.g. no server configured on a
 * plain local XAMPP), delivery falls back to a demo mode controlled by
 * AUTH_DEV_MODE below: the code/link is shown on screen in a clearly-labelled
 * box and logged to storage/mail.log, so the flow stays testable everywhere.
 *
 * Only a SHA-256 HASH of each token/code is stored in the database, never the
 * raw value, and every token is single-use with an expiry.
 */

// Set to false in production (and wire up real SMTP in deliverAuthMessage()).
define('AUTH_DEV_MODE', true);

define('RESET_TTL_MINUTES', 30);   // password reset link validity
define('TWOFA_TTL_MINUTES', 10);   // login code validity

/** Hash a raw token/code the same way it is stored. */
function hashToken(string $raw): string
{
    return hash('sha256', $raw);
}

/**
 * Create a password-reset token for a user, store its hash, and return the
 * RAW token (to embed in the reset link). Invalidates any earlier unused
 * tokens for that user first.
 */
function createPasswordResetToken(PDO $pdo, int $userId): string
{
    $pdo->prepare('UPDATE Password_Resets SET used_at = NOW()
                   WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);

    $raw = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO Password_Resets (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    )->execute([$userId, hashToken($raw), RESET_TTL_MINUTES]);

    return $raw;
}

/**
 * Validate a raw reset token. Returns the user_id if the token exists, is
 * unused, and is unexpired; otherwise null.
 */
function verifyPasswordResetToken(PDO $pdo, string $raw): ?int
{
    $stmt = $pdo->prepare(
        'SELECT user_id FROM Password_Resets
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([hashToken($raw)]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Mark a reset token as used (call once the password has been changed). */
function consumePasswordResetToken(PDO $pdo, string $raw): void
{
    $pdo->prepare('UPDATE Password_Resets SET used_at = NOW() WHERE token_hash = ?')
        ->execute([hashToken($raw)]);
}

/**
 * Generate a fresh 6-digit 2FA code for a user, store its hash, and return the
 * RAW code. Invalidates earlier unused codes first.
 */
function createTwoFactorCode(PDO $pdo, int $userId): string
{
    $pdo->prepare('UPDATE Two_Factor_Codes SET used_at = NOW()
                   WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare(
        'INSERT INTO Two_Factor_Codes (user_id, code_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    )->execute([$userId, hashToken($code), TWOFA_TTL_MINUTES]);

    return $code;
}

/**
 * Verify a submitted 2FA code for a user. On success marks it used and returns
 * true; otherwise false.
 */
function verifyTwoFactorCode(PDO $pdo, int $userId, string $code): bool
{
    $code = trim($code);
    $stmt = $pdo->prepare(
        'SELECT code_id FROM Two_Factor_Codes
         WHERE user_id = ? AND code_hash = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY code_id DESC LIMIT 1'
    );
    $stmt->execute([$userId, hashToken($code)]);
    $codeId = $stmt->fetchColumn();
    if ($codeId === false) {
        return false;
    }
    $pdo->prepare('UPDATE Two_Factor_Codes SET used_at = NOW() WHERE code_id = ?')
        ->execute([$codeId]);
    return true;
}

/**
 * Deliver an auth message (2FA code or reset link).
 *
 * Delivery order:
 *   1. Always append a copy to storage/mail.log (audit trail, works anywhere).
 *   2. If MAIL_ENABLED, send via PHPMailer/SMTP. On success return null so the
 *      calling page shows nothing sensitive on screen.
 *   3. If mail is disabled OR sending fails (e.g. no SMTP configured locally),
 *      fall back to demo mode: return the body so the page can display the
 *      code/link, keeping the whole flow testable without a mail server.
 *
 * @return string|null demo text to show on screen, or null when really emailed
 */
function deliverAuthMessage(string $toEmail, string $subject, string $body): ?string
{
    // (1) Audit copy — always.
    $logDir = __DIR__ . '/../storage';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents(
        $logDir . '/mail.log',
        '[' . date('Y-m-d H:i:s') . "] To: $toEmail | $subject\n$body\n\n",
        FILE_APPEND
    );

    // (2) Real delivery via PHPMailer, if configured.
    require_once __DIR__ . '/../config/mail.php';
    if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
        if (sendMailViaSmtp($toEmail, $subject, $body)) {
            return null; // sent for real — reveal nothing on screen
        }
        // fall through to demo mode if the send failed
    }

    // (3) Demo fallback (dev mode) — surface the payload to the page.
    if (defined('AUTH_DEV_MODE') && AUTH_DEV_MODE) {
        return $body;
    }
    return null;
}

/**
 * Send a plain-text email through PHPMailer over SMTP. Returns true on success.
 * Failures are logged (not thrown) so the caller can fall back gracefully.
 */
function sendMailViaSmtp(string $toEmail, string $subject, string $body): bool
{
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION; // 'tls' or 'ssl'
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body    = $body;           // plain text
        $mail->isHTML(false);

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        // Log and let the caller fall back to demo mode.
        @file_put_contents(
            __DIR__ . '/../storage/mail.log',
            '[' . date('Y-m-d H:i:s') . "] SMTP ERROR to $toEmail: "
            . ($mail->ErrorInfo ?: $e->getMessage()) . "\n\n",
            FILE_APPEND
        );
        return false;
    }
}
