<?php
/**
 * includes/auth.php
 *
 * Session and role-checking helpers, shared by every page that needs to
 * know "who is logged in and as what role". Built in Phase 1 so Phase 2
 * (login/register) has something to plug into, and every later dashboard
 * page can just call requireRole('employer') at the top instead of
 * re-writing session checks everywhere.
 *
 * Role values match Roles table seed data: 'employer', 'seeker', 'admin'.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * True if someone is logged in (a user_id exists in the session).
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Returns the logged-in user's role ('employer' | 'seeker' | 'admin'),
 * or null if nobody is logged in.
 */
function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Returns the logged-in user's id, or null if nobody is logged in.
 */
function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Returns the logged-in user's display name, or null if nobody is logged in.
 */
function currentUserName(): ?string
{
    return $_SESSION['full_name'] ?? null;
}

/**
 * Call at the top of any page that requires SOME logged-in user.
 * Sends a guest straight to the login page instead of letting them see
 * a broken/partial dashboard.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /auren/auth/login.php');
        exit;
    }
}

/**
 * Call at the top of any page that requires a SPECIFIC role.
 * e.g. requireRole('employer') at the top of employer/dashboard.php.
 * A logged-in seeker hitting an employer-only page is redirected to
 * their own dashboard rather than seeing an error — matches Requirements
 * Specification 4.2/4.3 (employer-only vs seeker-only features) and
 * Business Rules R2/R3 (only employers create jobs, only seekers apply).
 */
function requireRole(string $role): void
{
    requireLogin();
    if (currentRole() !== $role) {
        header('Location: /auren/includes/redirect_by_role.php');
        exit;
    }
}

/**
 * Logs the user out by clearing auth-specific session keys and rotating
 * the session ID. Deliberately does NOT call session_destroy() + a fresh
 * session_start(), because that requires the client to receive a brand
 * new session cookie mid-request — a flash message set right after logout
 * would be stored server-side in a session the browser has no cookie for,
 * and would silently never appear. session_regenerate_id(true) instead
 * keeps the same $_SESSION array (so a flash message set right after this
 * call still works) while issuing a new session ID — which is also the
 * standard defense against session fixation on a privilege change (login
 * or logout), independent of the flash-message concern.
 */
function logoutUser(): void
{
    unset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['full_name']);
    session_regenerate_id(true);
}
