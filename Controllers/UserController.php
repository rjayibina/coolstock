<?php
require_once __DIR__ . '/../Helpers/auth.php';
require_once __DIR__ . '/../Models/User.php';

/**
 * UserController.php
 * Admin-only account management for the User Access and Roles module.
 * Access itself is gated in index.php (the 'users' module is restricted
 * to the 'admin' role there) - this controller additionally protects
 * against an admin locking themselves or everyone else out, and against
 * one Administrator acting on another:
 *   - Self-protection: an admin can never change their own role through
 *     this module (create()/edit()), and can deactivate their own
 *     account only when at least one other active Administrator remains
 *     (guardLastActiveAdmin()) - never leaving the system with zero.
 *   - Peer-admin protection: an admin can only Edit/Deactivate Warehouse
 *     Staff and Technician accounts. Another Administrator account (not
 *     their own) can't be edited, deactivated, or promoted into via Edit
 *     at all - there's no admin hierarchy in the schema, so this is
 *     enforced here rather than by a DB-level role tier. New accounts CAN
 *     be created as Administrator (see CREATABLE_ROLES) - this only
 *     restricts changing an EXISTING account's role.
 */
class UserController
{
    private const PER_PAGE = 10;

    /** Roles selectable when creating a brand-new account. 'admin' is
     *  listed here because account CREATION is where the single-
     *  Administrator rule is enforced (see create()) - CREATABLE_ROLES
     *  itself doesn't limit it to zero-or-one, the countActiveAdmins()
     *  check in create() does. */
    private const CREATABLE_ROLES = ['admin', 'warehouse_staff', 'technician'];

    /** Roles an Administrator is allowed to move an EXISTING account
     *  into via Edit. 'admin' is deliberately excluded - promoting
     *  someone to Administrator has to happen at account-creation time
     *  (see CREATABLE_ROLES), not by editing an existing lower-tier
     *  account into one. */
    private const ASSIGNABLE_ROLES = ['warehouse_staff', 'technician'];

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
        $activeAdminCount = $this->user->countActiveAdmins();

        $pagination = [
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ];

        // AJAX pagination: render the same view into a buffer and return it
        // as JSON instead of a full page. See Views/users/index.php's
        // $ajaxFragment guard.
        if (is_ajax_request()) {
            $ajaxFragment = true;
            ob_start();
            require __DIR__ . '/../Views/users/index.php';
            $html = ob_get_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'html' => $html,
                'data' => array_column($users, null, 'user_id'),
            ]);
            return;
        }

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

        if (!$error && !in_array($role, self::CREATABLE_ROLES, true)) {
            $error = "Please choose a valid role.";
        }

        // Single-Administrator rule: the system allows exactly one
        // Administrator account. Once it exists (and it always does once
        // created - guardLastActiveAdmin() blocks the last active admin
        // from ever being deactivated), no second one can be created.
        if (!$error && $role === 'admin' && $this->user->countActiveAdmins() >= 1) {
            $error = "Only one Administrator account is allowed, and one already exists.";
        }

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
     *  is only changed when a new one is actually typed in. Active/Inactive
     *  is NOT editable from this form - the row-level Deactivate/Reactivate
     *  buttons on the Users list are the single path for that (each with
     *  their own guardLastActiveAdmin()/peer-admin protection), so editing
     *  a user's name/email/role can never have the side effect of also
     *  flipping their active status. */
    public function edit(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = (string) ($_POST['password'] ?? '');

        if ($id <= 0) {
            header("Location: index.php?module=users&action=index");
            exit;
        }

        $target = $this->user->readById($id);
        if (!$target) {
            header("Location: index.php?module=users&action=index");
            exit;
        }

        $isActive = (int) $target['is_active'] === 1;

        $current = current_user();
        $isSelf = $current && (int) $current['user_id'] === $id;

        // Peer-admin protection: another Administrator's account can't be
        // touched through this module at all, checked before anything
        // else so a peer admin's name/email can't be changed either.
        if (!$isSelf && $target['role'] === 'admin') {
            header("Location: index.php?module=users&action=index&status=error&message=" . urlencode("You cannot modify another Administrator's account."));
            exit;
        }

        // Self-protection: a signed-in admin can update their own name,
        // email, and password here, but never their own role (forced back
        // to its current value regardless of what the form submitted).
        if ($isSelf) {
            $role = $target['role'];
        } elseif (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            // Editing someone else: can only ever leave/set them at a
            // lower-tier role - never promote to Administrator.
            header("Location: index.php?module=users&action=index&status=error&message=" . urlencode("You can only assign the Warehouse Staff or Technician role."));
            exit;
        }

        $error = $this->validate($name, $email, $role, $password, false);

        if (!$error && $this->user->emailExists($email, $id)) {
            $error = "That email is already in use.";
        }

        if (!$error && !$isSelf) {
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
        // session's display name in sync instead of showing a stale
        // value until their next login (role can't change here, so it
        // never needs re-syncing).
        if ($isSelf) {
            $_SESSION['full_name'] = $name;
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
            $isSelf = $current && (int) $current['user_id'] === $id;

            // Peer-admin protection still applies to another admin's
            // account - but an admin deactivating THEMSELF is allowed,
            // as long as they're not the last active one (checked next).
            $target = $this->user->readById($id);
            if ($target && $target['role'] === 'admin' && !$isSelf) {
                header("Location: index.php?module=users&action=index&status=error&message=" . urlencode("You cannot deactivate another Administrator's account."));
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
            $current = current_user();
            $target = $this->user->readById($id);
            $isSelf = $current && (int) $current['user_id'] === $id;
            if ($target && $target['role'] === 'admin' && !$isSelf) {
                header("Location: index.php?module=users&action=index&status=error&message=" . urlencode("You cannot modify another Administrator's account."));
                exit;
            }
        }

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
