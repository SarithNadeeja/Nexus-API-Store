<?php

declare(strict_types=1);

/**
 * One-time database permission repair.
 * Visit /fix-db-permissions.php once, then DELETE this file.
 *
 * Optional in config.php for automatic repair:
 * 'db_admin' => ['user' => 'postgres', 'pass' => '...', 'database' => 'nexus']
 */

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    exit('Create config.php first.');
}

$config = require $configPath;
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/AdminService.php';

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
        $pdo->exec('ALTER TABLE ' . $quotedTable . ' OWNER TO ' . $quotedUser);
        $pdo->exec('ALTER SEQUENCE ' . quotePgIdentifier($table . '_id_seq') . ' OWNER TO ' . $quotedUser);
    }

    $pdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO ' . $quotedUser);
    $pdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO ' . $quotedUser);
}

function recreateSchema(PDO $pdo, string $sqlFile): void
{
    $pdo->exec('DROP TABLE IF EXISTS email_verification_tokens CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS api_purchases CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS coin_transactions CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS api_listings CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS app_users CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS admin_users CASCADE');
    $pdo->exec('DROP TABLE IF EXISTS categories CASCADE');

    $sql = file_get_contents($sqlFile);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '' && !str_starts_with($statement, '--')) {
            $pdo->exec($statement);
        }
    }
}

function tableOwners(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT tablename, tableowner
         FROM pg_tables
         WHERE schemaname = 'public'
         ORDER BY tablename"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function canReadApis(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM api_listings LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

$repair = isset($_GET['repair']) && $_GET['repair'] === '1';
$sqlFile = dirname(__DIR__) . '/database.sql';
if (!file_exists($sqlFile)) {
    $sqlFile = __DIR__ . '/../database.sql';
}

$messages = [];
$errors = [];

try {
    $dbUser = $config['db']['user'];
    $pdo = Database::connect($config['db']);

    if ($repair) {
        try {
            recreateSchema($pdo, $sqlFile);
            applyPostgresGrants($pdo, $dbUser);
            AdminService::ensureDefaultAdmin($pdo);
            $messages[] = 'Schema recreated and permissions applied using app user.';
        } catch (Throwable $e) {
            $errors[] = 'Repair with app user failed: ' . $e->getMessage();
        }
    }

    if (!canReadApis($pdo) && !empty($config['db_admin'])) {
        $admin = $config['db_admin'];
        $adminDsn = Database::buildDsn(
            array_merge($config['db'], $admin),
            'pgsql',
            $admin['database'] ?? $config['db']['name']
        );
        $adminPdo = new PDO($adminDsn, $admin['user'], $admin['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        applyPostgresGrants($adminPdo, $dbUser);
        $messages[] = 'Permissions fixed using db_admin credentials.';
    } elseif (!canReadApis($pdo) && !$repair) {
        try {
            applyPostgresGrants($pdo, $dbUser);
            $messages[] = 'Attempted to apply grants using app user.';
        } catch (Throwable $e) {
            $errors[] = 'Grant attempt failed: ' . $e->getMessage();
        }
    }

    $pdo = Database::connect($config['db']);
    $owners = tableOwners($pdo);
    $readable = canReadApis($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Database permission fix failed</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fix DB Permissions</title>
  <style>
    body { font-family: Arial, sans-serif; background:#0b1020; color:#e5e7eb; padding:2rem; }
    .ok { color:#34d399; }
    .bad { color:#f87171; }
    pre, code { background:#111827; padding:1rem; border-radius:.5rem; display:block; overflow:auto; }
    a { color:#22d3ee; }
  </style>
</head>
<body>
  <h1>Database Permission Check</h1>

  <?php foreach ($messages as $message): ?>
    <p class="ok"><?= htmlspecialchars($message) ?></p>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <p class="bad"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <p>API table readable: <strong class="<?= $readable ? 'ok' : 'bad' ?>"><?= $readable ? 'YES' : 'NO' ?></strong></p>

  <h2>Table Owners</h2>
  <pre><?php
    foreach ($owners as $row) {
        echo htmlspecialchars($row['tablename'] . ' -> ' . $row['tableowner']) . "\n";
    }
  ?></pre>

  <?php if (!$readable): ?>
    <h2>Option 1: Repair with app user</h2>
    <p>If tables are owned by <code><?= htmlspecialchars($dbUser) ?></code>, recreate them:</p>
    <p><a href="?repair=1">Run repair (drops and recreates all tables)</a></p>

    <h2>Option 2: Run as PostgreSQL superuser</h2>
    <p>SSH into the server and run:</p>
    <pre>psql -U postgres -d <?= htmlspecialchars($config['db']['name']) ?> -f database-grants.sql</pre>

    <h2>Option 3: Add admin credentials to config.php</h2>
    <pre>'db_admin' => [
    'user' => 'postgres',
    'pass' => 'your-postgres-password',
    'database' => '<?= htmlspecialchars($config['db']['name']) ?>',
],</pre>
    <p>Then reload this page.</p>
  <?php else: ?>
    <p class="ok">Permissions look good. Test <a href="/api/public/apis">/api/public/apis</a> then delete this file.</p>
  <?php endif; ?>
</body>
</html>
