<?php
require_once __DIR__ . '/config.php';

date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'Africa/Lagos');

final class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
            $dbname = defined('DB_NAME') ? DB_NAME : 'uiconf';
            $username = defined('DB_USER') ? DB_USER : 'root';
            $password = defined('DB_PASS') ? DB_PASS : '';

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                $pdo->exec("SET time_zone = '+01:00'");
                self::$instance = $pdo;
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                http_response_code(500);
                exit("A database error occurred.");
            }
        }

        return self::$instance;
    }
}

function db(): PDO {
    return Database::getConnection();
}
