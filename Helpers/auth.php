<?php
/**
 * Helpers/auth.php
 * Session bootstrap + small helpers for the User Access and Roles module.
 * Required once from index.php, before any controller runs, so every
 * request has session data available and every view/controller can call
 * these without re-requiring anything.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Is anyone signed in on this session? */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** The signed-in user's session data, or null if nobody is signed in.
 *  Only what's needed for display/checks - not a full User record. */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return [
        'user_id' => (int) $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ];
}

/** Does the signed-in user hold any of the given roles? */
function has_role(string ...$roles): bool
{
    $current = current_user();
    return $current !== null && in_array($current['role'], $roles, true);
}

/** Is this an AJAX request (used to serve a partial list/table fragment
 *  instead of a full page - see the pagination pattern in index.php's
 *  listing controllers)? */
function is_ajax_request(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}
