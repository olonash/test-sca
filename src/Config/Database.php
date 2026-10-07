<?php

declare(strict_types=1);

namespace ScalE\Config;

use PDO;
use PDOException;

final class Database
{
    public static function getConnection(): PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $db = getenv('DB_DATABASE') ?: 'scal_e_cdp';
        $user = getenv('DB_USERNAME') ?: 'scal_e';
        $pass = getenv('DB_PASSWORD') ?: 'scal_e_password';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $db
        );

        try {
            $pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new PDOException('Database connection failed: ' . $exception->getMessage());
        }

        return $pdo;
    }
}
