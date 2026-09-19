<?php
require_once __DIR__ . '/../Config/Database.php';

/**
 * User.php (Model)
 * Backs the User Access and Roles module: one row per system user, with
 * a bcrypt password hash and a role that gates which modules/actions
 * they can reach (enforced in index.php, not here).
 */
class User
{
    private PDO $conn;
    private string $table = "users";

    public ?int $user_id = null;
    public ?string $full_name = null;
    public ?string $email = null;
    public ?string $password_hash = null;
    public ?string $role = null;
    public bool $is_active = true;

    /** Matches the `role` ENUM in coolstock_full_setup.sql. */
    public const ROLES = [
        'admin' => 'Administrator',
        'warehouse_staff' => 'Warehouse Staff',
        'technician' => 'Technician',
    ];

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }

    /** READ - single user by email, used for login. Returns the raw row
     *  (including password_hash) since only the login check needs it. */
    public function readByEmail(string $email): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** READ - single user by ID */
    public function readById(int $id): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Is this email already taken by another account? */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = "SELECT user_id FROM {$this->table} WHERE email = :email";
        if ($excludeId !== null) {
            $query .= " AND user_id != :excludeId";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($excludeId !== null) {
            $stmt->bindParam(':excludeId', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (bool) $stmt->fetch();
    }

    public function count(): int
    {
        return (int) $this->conn->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }

    /** How many currently-active Administrator accounts exist - used to
     *  block the last one from being demoted or deactivated. */
    /** Headcount per role, split by active/deactivated - for the
     *  Administrator dashboard's team overview.
     *
     *  Normalized against ROLES so every role always appears, even with
     *  nobody in it (same approach as Transaction::countByType()) - a
     *  role silently vanishing from the table would read as a bug rather
     *  than as "no technicians yet". One grouped query, not one per role.
     *
     *  Returns role => ['active' => int, 'inactive' => int, 'total' => int]. */
    public function countsByRole(): array
    {
        $counts = [];
        foreach (array_keys(self::ROLES) as $role) {
            $counts[$role] = ['active' => 0, 'inactive' => 0, 'total' => 0];
        }

        $stmt = $this->conn->query(
            "SELECT role, is_active, COUNT(*) AS total
             FROM {$this->table}
             GROUP BY role, is_active"
        );

        foreach ($stmt->fetchAll() as $row) {
            $role = $row['role'];
            if (!isset($counts[$role])) {
                // A role in the table that isn't in ROLES shouldn't happen
                // (the column is an ENUM) but is shown rather than dropped.
                $counts[$role] = ['active' => 0, 'inactive' => 0, 'total' => 0];
            }
            $bucket = (int) $row['is_active'] === 1 ? 'active' : 'inactive';
            $counts[$role][$bucket] += (int) $row['total'];
            $counts[$role]['total'] += (int) $row['total'];
        }

        return $counts;
    }

    public function countActiveAdmins(): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE role = 'admin' AND is_active = 1"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private const SORT_OPTIONS = [
        'newest' => 'user_id DESC',
        'oldest' => 'user_id ASC',
        'name_asc' => 'full_name ASC',
        'name_desc' => 'full_name DESC',
    ];

    /** READ - every user, sorted/paginated for the Users list page */
    public function readAll(?string $sort = null, ?int $limit = null, ?int $offset = null): array
    {
        $orderBy = self::SORT_OPTIONS[$sort] ?? self::SORT_OPTIONS['newest'];
        $query = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";

        if ($limit !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->conn->prepare($query);
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** CREATE - insert a new user (password_hash must already be hashed) */
    public function create(): bool
    {
        $query = "INSERT INTO {$this->table} (full_name, email, password_hash, role, is_active)
                  VALUES (:full_name, :email, :password_hash, :role, :is_active)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindValue(':is_active', $this->is_active ? 1 : 0, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** UPDATE - profile fields only (name/email/role/active). Password
     *  changes go through updatePassword() so a blank password field on
     *  the Edit form never accidentally wipes a working password. */
    public function update(): bool
    {
        $query = "UPDATE {$this->table}
                  SET full_name = :full_name, email = :email, role = :role, is_active = :is_active
                  WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindValue(':is_active', $this->is_active ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updatePassword(int $userId, string $passwordHash): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET password_hash = :password_hash WHERE user_id = :user_id"
        );
        $stmt->bindParam(':password_hash', $passwordHash);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Activate/deactivate - accounts are never hard-deleted, so their
     *  name stays attributable on anything historical they touched. */
    public function toggleActive(int $userId, bool $active): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table} SET is_active = :is_active WHERE user_id = :user_id"
        );
        $stmt->bindValue(':is_active', $active ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
