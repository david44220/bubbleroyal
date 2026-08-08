<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    public static function fromEnvironment(): PDO
    {
        $dsn = trim((string) (getenv('DB_DSN') ?: ''));
        $username = (string) (getenv('DB_USERNAME') ?: getenv('MYSQL_USER') ?: '');
        $password = (string) (getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '');

        if ($dsn === '') {
            $host = trim((string) (getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: ''));
            $port = trim((string) (getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: '3306'));
            $database = trim((string) (getenv('DB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: ''));
            if ($host === '' || $database === '') {
                throw new RuntimeException('Database configuration is incomplete.');
            }
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND') && str_starts_with($dsn, 'mysql:')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET SESSION time_zone = '+00:00'";
        }

        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }
    }
}
