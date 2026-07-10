<?php

declare(strict_types=1);

/**
 * One-time database permission repair.
 * Visit /fix-db-permissions.php once, then DELETE this file.
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

function freshPdo(array $db, ?string $database = null): PDO
{
    $dsn = Database::buildDsn($db, 'pgsql', $database ?? $db['name']);

    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function applyPostgresGrants(PDO $pdo, string $dbUser): void
{
    $quotedUser = quotePgIdentifier($dbUser);
    $pdo->exec('GRANT USAGE, CREATE ON SCHEMA public TO ' . $quotedUser);
    $pdo->exec('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ' . $quotedUser);
    $pdo->exec('GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ' . $quotedUser);

    foreach (pgTables() as $table) {
        $quotedTable = quotePgIdentifier($table);
        $quotedSeq = quotePgIdentifier($table . '_id_seq');
        try {
            $pdo->exec('GRANT ALL PRIVILEGES ON TABLE ' . $quotedTable . ' TO ' . $quotedUser);
            $pdo->exec('GRANT USAGE, SELECT, UPDATE ON SEQUENCE ' . $quotedSeq . ' TO ' . $quotedUser);
            $pdo->exec('ALTER TABLE ' . $quotedTable . ' OWNER TO ' . $quotedUser);
            $pdo->exec('ALTER SEQUENCE ' . $quotedSeq . ' OWNER TO ' . $quotedUser);
        } catch (Throwable) {
            // Continue with remaining tables.
        }
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

function connectAdminPdo(array $config, string $adminUser, string $adminPass): PDO
{
    $db = $config['db'];
    $adminDb = $config['db_admin']['database'] ?? $db['name'];
    $dsn = Database::buildDsn(
        array_merge($db, ['user' => $adminUser, 'pass' => $adminPass]),
        'pgsql',
        $adminDb
    );

    return new PDO($dsn, $adminUser, $adminPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

$sqlFile = dirname(__DIR__) . '/database.sql';
if (!file_exists($sqlFile)) {
    $sqlFile = __DIR__ . '/../database.sql';
}

$dbUser = $config['db']['user'];
$messages = [];
$errors = [];
$readable = false;
$owners = [];

try {
    $pdo = freshPdo($config['db']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'admin-grant') {
            $adminUser = trim((string) ($_POST['admin_user'] ?? 'postgres'));
            $adminPass = (string) ($_POST['admin_pass'] ?? '');
            if ($adminUser === '' || $adminPass === '') {
                throw new RuntimeException('PostgreSQL admin username and password are required.');
            }
            $adminPdo = connectAdminPdo($config, $adminUser, $adminPass);
            applyPostgresGrants($adminPdo, $dbUser);
            $messages[] = 'Permissions fixed using PostgreSQL admin account.';
        }

        if ($action === 'recreate') {
            recreateSchema($pdo, $sqlFile);
            applyPostgresGrants($pdo, $dbUser);
            AdminService::ensureDefaultAdmin($pdo);
            $messages[] = 'Tables recreated successfully as user ' . $dbUser . '.';
        }
    }

    if (!canReadApis($pdo) && empty($messages)) {
        if (!empty($config['db_admin']['user']) && !empty($config['db_admin']['pass'])) {
            try {
                $adminPdo = connectAdminPdo(
                    $config,
                    $config['db_admin']['user'],
                    $config['db_admin']['pass']
                );
                applyPostgresGrants($adminPdo, $dbUser);
                $messages[] = 'Permissions fixed using db_admin from config.php.';
            } catch (Throwable $e) {
                $errors[] = 'db_admin grant failed: ' . $e->getMessage();
            }
        } else {
            try {
                applyPostgresGrants($pdo, $dbUser);
                $messages[] = 'Attempted grants with app user.';
            } catch (Throwable $e) {
                $errors[] = 'App-user grant failed: ' . $e->getMessage();
            }

            if (!canReadApis($pdo)) {
                try {
                    recreateSchema($pdo, $sqlFile);
                    AdminService::ensureDefaultAdmin($pdo);
                    $messages[] = 'Auto-repair: recreated all tables as ' . $dbUser . '.';
                } catch (Throwable $e) {
                    $errors[] = 'Auto-repair recreate failed: ' . $e->getMessage();
                }
            }
        }
    }

    $pdo = freshPdo($config['db']);
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
    body { font-family: Arial, sans-serif; background:#0b1020; color:#e5e7eb; padding:2rem; max-width:900px; margin:0 auto; }
    .ok { color:#34d399; }
    .bad { color:#f87171; }
    pre, code { background:#111827; padding:1rem; border-radius:.5rem; display:block; overflow:auto; }
    a { color:#22d3ee; }
    .card { background:#111827; border:1px solid #1f2937; border-radius:1rem; padding:1.25rem; margin:1rem 0; }
    input { width:100%; padding:.75rem; border-radius:.5rem; border:1px solid #374151; background:#0f172a; color:#fff; margin:.5rem 0 1rem; }
    button { background:linear-gradient(135deg,#22d3ee,#3b82f6); color:#fff; border:0; padding:.8rem 1.2rem; border-radius:.75rem; font-weight:600; cursor:pointer; }
    .btn-danger { background:#dc2626; }
    label { font-size:.9rem; color:#cbd5e1; }
  </style>
</head>
<body>
  <h1>Database Permission Repair</h1>

  <?php foreach ($messages as $message): ?>
    <p class="ok">✓ <?= htmlspecialchars($message) ?></p>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <p class="bad">✗ <?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <p>API table readable: <strong class="<?= $readable ? 'ok' : 'bad' ?>"><?= $readable ? 'YES' : 'NO' ?></strong></p>

  <h2>Table Owners</h2>
  <pre><?php
    if ($owners) {
        foreach ($owners as $row) {
            echo htmlspecialchars($row['tablename'] . ' -> ' . $row['tableowner']) . "\n";
        }
    } else {
        echo "No tables found in public schema.\n";
    }
  ?></pre>

  <?php if ($readable): ?>
    <p class="ok">Done. Test <a href="/api/public/apis">/api/public/apis</a>, then delete <code>fix-db-permissions.php</code>.</p>
  <?php else: ?>
    <div class="card">
      <h2>Fix with PostgreSQL admin (recommended)</h2>
      <p>Use your server postgres password once. It is not saved.</p>
      <form method="post">
        <input type="hidden" name="action" value="admin-grant">
        <label>Admin username</label>
        <input type="text" name="admin_user" value="postgres" required>
        <label>Admin password</label>
        <input type="password" name="admin_pass" required>
        <button type="submit">Grant permissions to <?= htmlspecialchars($dbUser) ?></button>
      </form>
    </div>

    <div class="card">
      <h2>Recreate tables (deletes all data)</h2>
      <p>Only use this if the admin grant above fails.</p>
      <form method="post" onsubmit="return confirm('This deletes all database data. Continue?');">
        <input type="hidden" name="action" value="recreate">
        <button type="submit" class="btn-danger">Drop and recreate all tables</button>
      </form>
    </div>

    <div class="card">
      <h2>SSH command</h2>
      <pre>sudo -u postgres psql -d <?= htmlspecialchars($config['db']['name']) ?> -c "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO <?= htmlspecialchars($dbUser) ?>; GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO <?= htmlspecialchars($dbUser) ?>; ALTER TABLE categories OWNER TO <?= htmlspecialchars($dbUser) ?>; ALTER TABLE api_listings OWNER TO <?= htmlspecialchars($dbUser) ?>; ALTER TABLE admin_users OWNER TO <?= htmlspecialchars($dbUser) ?>; ALTER TABLE app_users OWNER TO <?= htmlspecialchars($dbUser) ?>; ALTER TABLE coin_transactions OWNER TO <?= htmlspecialchars($dbUser) ?>; ALTER TABLE api_purchases OWNER TO <?= htmlspecialchars($dbUser) ?>; ALTER TABLE email_verification_tokens OWNER TO <?= htmlspecialchars($dbUser) ?>;"</pre>
    </div>
  <?php endif; ?>
</body>
</html>
