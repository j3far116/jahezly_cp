<?php
namespace App\Core;

use PDO; use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            $dsn  = $_ENV['DB_DSN']  ?? '';
            $user = $_ENV['DB_USER'] ?? '';
            $pass = $_ENV['DB_PASS'] ?? '';
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                    echo 'DB connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                } else {
                    echo 'DB connection failed.';
                }
                exit;
            }
        }
        return self::$pdo;
    }
}