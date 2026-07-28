<?php
/**
 * includes/flash.php
 *
 * Tiny "flash message" helper: set a message before a redirect, display it
 * once on the next page load, then it's gone. Used for things like
 * "Registration successful, please log in" or "Invalid email or password".
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * @param string $type 'success' | 'danger' | 'warning' | 'info' (Bootstrap alert classes)
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Renders (and clears) the pending flash message, if any. Call this once,
 * right after including header.php, on any page that might show one.
 */
function renderFlash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $type = htmlspecialchars($_SESSION['flash']['type']);
    $message = htmlspecialchars($_SESSION['flash']['message']);
    unset($_SESSION['flash']);
    echo '<div class="container mt-3">'
        . '<div class="alert alert-' . $type . ' alert-dismissible alert-auto-dismiss" role="alert">'
        . $message
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
        . '</div></div>';
}
