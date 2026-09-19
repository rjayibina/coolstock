<?php
require_once __DIR__ . '/../Helpers/auth.php';
require_once __DIR__ . '/../Models/User.php';

/**
 * UserController.php
 * Admin-only account management for the User Access and Roles module.
 * Access itself is gated in index.php (the 'users' module is restricted
 * to the 'admin' role there) - this controller additionally protects
 * against an admin locking themselves or everyone else out.
 */
class UserController
{
    private const PER_PAGE = 10;

    private User $user;

    public function __construct()
    {
        $this->user = new User();
    }

    /** List all users, sorted and paginated */
    public function index(): void
    {
        $sort = $_GET['sort'] ?? 'newest';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalCount = $this->user->count();
        $totalPages = max(1, (int) ceil($totalCount / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $users = $this->user->readAll($sort, self::PER_PAGE, $offset);

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];

        require __DIR__ . '/../Views/users/index.php';
    }

    /** Create a user account (called from the Add User modal) */
    public function create(): void
    {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = (string) ($_POST['password'] ?? '');

        $error = $this->validate($name, $email, $role, $password, true);

        if (!$error && $this->user->emailExists($email)) {
            $error = "That email is already in use.";
        }

        if ($error) {
            header("Location: index.php?module=users&action=index&status=error&message=" . urlencode($error));
            exit;
        }

        $this->user->full_name = $name;
        $this->user->email = $email;
        $this->user->role = $role;
        $this->user->is_active = true;
        $this->user->password_hash = password_hash($password, PASSWORD_DEFAULT);

        if ($this->user->create()) {
            header("Location: index.php?module=users&action=index&status=created");
            exit;
        }

        header("Location: index.php?module=users&action=index&status=error");
        exit;
    }

    /** Update a user account (called from the Edit User modal). Password
     *  is only changed when a new one is actually typed in. */
    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = (string) ($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']);

        if ($id <= 0) {
            header("Location: index.php?module=users&action=index");
            exit;
        }

        $error = $this->validate($name, $email, $role, $password, false);

        if (!$error && $this->user->emailExists($email, $id)) {
            $error = "That email is already in use.";
        }

        if (!$error) {
            $error = $this->guardLastActiveAdmin($id, $role, $isActive);
        }

        if ($error) {
            header("Location: index.php?module=users&action=index&status=error&message=" . urlencode($error));
            exit;
        }

        $this->user->user_id = $id;
        $this->user->full_name = $name;
        $this->user->email = $email;
        $this->user->role = $role;
        $this->user->is_active = $isActive;
        $this->user->update();

        if ($password !== '') {
            $this->user->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
        }

        // If the signed-in admin just edited their own account, keep the
        // session's display name/role in sync instead of showing stale
        // values until their next login.
        $current = current_user();
        if ($current && $current['user_id'] === $id) {
            $_SESSION['full_name'] = $name;
            $_SESSION['role'] = $role;
        }

        header("Location: index.php?module=users&action=index&status=updated");
        exit;
    }

    /** Deactivate an account - never a hard delete, so the name stays
     *  attributable on anything historical it was involved with. */
    public function delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $current = current_user();
            if ($current && $current['user_id'] === $id) {
                header("Location: index.php?module=users&action=index&status=error&message=" . urlencode("You cannot deactivate your own account."));
                exit;
            }

            $error = $this->guardLastActiveAdmin($id, null, false);
            if ($error) {
                header("Location: index.php?module=users&action=index&status=error&message=" . urlencode($error));
                exit;
            }

            $this->user->toggleActive($id, false);
        }

        header("Location: index.php?module=users&action=index&status=deactivated");
        exit;
    }

    /** Reactivate a previously deactivated account */
    public function reactivate(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $this->user->toggleActive($id, true);
        }

        header("Location: index.php?module=users&action=index&status=activated");
        exit;
    }

    private function validate(string $name, string $email, string $role, string $password, bool $isCreate): ?string
    {
        if ($name === '') {
            return "Full name is required.";
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "A valid email address is required.";
        }
        if (!array_key_exists($role, User::ROLES)) {
            return "A valid role is required.";
        }
        if ($isCreate && strlen($password) < 8) {
            return "Password must be at least 8 characters.";
        }
        if (!$isCreate && $password !== '' && strlen($password) < 8) {
            return "New password must be at least 8 characters.";
        }
        return null;
    }

    /** Blocks demoting/deactivating the last active Administrator, which
     *  would leave nobody able to manage accounts at all. $newRole/
     *  $newIsActive are null/the value the edit form is about to apply;
     *  pass $newRole = null from delete() since role isn't changing there. */
    private function guardLastActiveAdmin(int $id, ?string $newRole, bool $newIsActive): ?string
    {
        $current = $this->user->readById($id);
        if (!$current || $current['role'] !== 'admin' || (int) $current['is_active'] !== 1) {
            return null; // wasn't an active admin to begin with
        }

        $stillActiveAdmin = ($newRole === null || $newRole === 'admin') && $newIsActive;
        if (!$stillActiveAdmin && $this->user->countActiveAdmins() <= 1) {
            return "At least one active Administrator account must remain.";
        }

        return null;
    }
}
