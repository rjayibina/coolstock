<?php
/**
 * Database.php
 * Handles the PDO connection to the mister_aircon MySQL database.
 * Uses the Singleton pattern so only one connection is ever open.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $conn;

    private string $host     = "localhost";
    private string $db_name  = "mister_aircon";
    private string $username = "root";
    private string $password = "";

    private function __construct()
    {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    // Prevent cloning of the instance (Singleton rule)
    private function __clone() {}

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->conn;
    }
}
