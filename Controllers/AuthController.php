<?php
require_once __DIR__ . '/../Helpers/auth.php';
require_once __DIR__ . '/../Models/User.php';

/**
 * AuthController.php
 * Login/logout for the User Access and Roles module. Session state
 * itself (session_start, current_user(), has_role()) lives in
 * Helpers/auth.php since index.php needs those before any controller
 * is resolved.
 */
class AuthController
{
    private User $user;

    public function __construct()
    {
        $this->user = new User();
    }

    /** Show the login form (or bounce to the dashboard if already
     *  signed in - there's nothing else on this page for them). */
    public function index(): void
    {
        if (is_logged_in()) {
            header("Location: index.php?module=dashboard");
            exit;
        }

        $error = $_GET['error'] ?? null;
        require __DIR__ . '/../Views/auth/login.php';
    }

    /** Handle the login form submission */
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: index.php?module=auth&action=index");
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $record = $email !== '' ? $this->user->readByEmail($email) : null;

        if (!$record || !(int) $record['is_active'] || !password_verify($password, $record['password_hash'])) {
            header("Location: index.php?module=auth&action=index&error=invalid");
            exit;
        }

        // Regenerate the session ID on every successful login so a
        // pre-login session token can never be reused post-login
        // (session fixation).
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $record['user_id'];
        $_SESSION['full_name'] = $record['full_name'];
        $_SESSION['email'] = $record['email'];
        $_SESSION['role'] = $record['role'];

        header("Location: index.php?module=dashboard");
        exit;
    }

    /** Log out and destroy the session */
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        header("Location: index.php?module=auth&action=index");
        exit;
    }
}
