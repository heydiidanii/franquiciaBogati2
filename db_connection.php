<?php
require_once __DIR__ . '/config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_NAME
            );

            $this->connection = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            // En producción esto debería ir a un log
            die('Error de conexión a la base de datos.');
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }

        return self::$instance->connection;
    }

    // Evitar clonación
    private function __clone() {}

    // Evitar deserialización (PHP 8+)
    public function __wakeup(): void
    {
        throw new Exception("No se puede deserializar Database");
    }
}
