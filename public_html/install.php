<?php

declare(strict_types=1);

/**
 * One-time installer.
 * 1. Copy config.sample.php to config.php and fill database + mail settings.
 * 2. Visit /install.php once, then DELETE this file.
 */

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    exit('Create config.php from config.sample.php first.');
}

$config = require $configPath;
$sqlFile = dirname(__DIR__) . '/database.sql';
if (!file_exists($sqlFile)) {
    $sqlFile = __DIR__ . '/../database.sql';
}

function quotePgIdentifier(string $value): string
{
    return '"' . str_replace('"', '""', $value) . '"';
}

function pgTables(): array
{
    return [
        'categories',
        'api_listings',
        'admin_users',
        'app_users',
        'coin_transactions',
        'api_purchases',
        'email_verification_tokens',
    ];
}

function applyPostgresGrants(PDO $pdo, string $dbUser): void
{
    $quotedUser = quotePgIdentifier($dbUser);
    $pdo->exec('GRANT USAGE ON SCHEMA public TO ' . $quotedUser);

    foreach (pgTables() as $table) {
        $quotedTable = quotePgIdentifier($table);
        $pdo->exec('GRANT ALL PRIVILEGES ON TABLE ' . $quotedTable . ' TO ' . $quotedUser);
        $pdo->exec('GRANT USAGE, SELECT, UPDATE ON SEQUENCE ' . quotePgIdentifier($table . '_id_seq') . ' TO ' . $quotedUser);
    }

    $pdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO ' . $quotedUser);
    $pdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO ' . $quotedUser);
}

function ensurePostgresDatabase(array $db): void
{
    $adminDb = $db['admin_database'] ?? 'postgres';
    $adminDsn = Database::buildDsn($db, 'pgsql', $adminDb);
    $admin = new PDO($adminDsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $stmt->execute([$db['name']]);
    if (!$stmt->fetchColumn()) {
        $admin->exec('CREATE DATABASE ' . quotePgIdentifier($db['name']));
    }
}

try {
    require_once __DIR__ . '/includes/Database.php';
    require_once __DIR__ . '/includes/AdminService.php';

    $driver = $config['db']['driver'] ?? 'pgsql';

    if ($driver === 'pgsql') {
        ensurePostgresDatabase($config['db']);
    } else {
        $mysql = new PDO(
            sprintf('mysql:host=%s;charset=%s', $config['db']['host'], $config['db']['charset'] ?? 'utf8mb4'),
            $config['db']['user'],
            $config['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $dbName = str_replace('`', '``', $config['db']['name']);
        $mysql->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    $pdo = Database::connect($config['db']);

    $sql = file_get_contents($sqlFile);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '' && !str_starts_with($statement, '--')) {
            $pdo->exec($statement);
        }
    }

    if ($driver === 'pgsql') {
        applyPostgresGrants($pdo, $config['db']['user']);
    }

    AdminService::ensureDefaultAdmin($pdo);

    echo '<h1>Nexus API Store installed successfully</h1>';
    echo '<p>Database driver: <strong>' . htmlspecialchars($driver) . '</strong></p>';
    echo '<p>On first admin login you will be asked to set a new username and password.</p>';
    echo '<p><a href="/nexus-admin/login.php">Open Admin Panel</a> | <a href="/index.html">Open Website</a></p>';
    echo '<p style="color:#ef4444;"><strong>Delete install.php now for security.</strong></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Install failed</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
