<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $db): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = $db['driver'] ?? 'pgsql';
        $dsn = self::buildDsn($db, $driver);

        self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }

    public static function lastInsertId(PDO $pdo, string $table, string $column = 'id'): int
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            return (int) $pdo->lastInsertId($table . '_' . $column . '_seq');
        }

        return (int) $pdo->lastInsertId();
    }

    public static function buildDsn(array $db, ?string $driver = null, ?string $database = null): string
    {
        $driver = $driver ?? ($db['driver'] ?? 'pgsql');
        $database = $database ?? $db['name'];

        if ($driver === 'pgsql') {
            $port = (int) ($db['port'] ?? 5432);

            return sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $db['host'],
                $port,
                $database
            );
        }

        return sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'],
            $database,
            $db['charset'] ?? 'utf8mb4'
        );
    }
}
