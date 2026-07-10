<?php

declare(strict_types=1);

/**
 * One-time coin tables migration.
 * Visit /migrate-coins.php once, then DELETE this file.
 */

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    exit('Create config.php first.');
}

$config = require $configPath;
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/CoinPackageService.php';

function quotePgIdentifier(string $value): string
{
    return '"' . str_replace('"', '""', $value) . '"';
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

function grantCoinSchemaAccess(PDO $adminPdo, string $dbUser): void
{
    $quotedUser = quotePgIdentifier($dbUser);
    $adminPdo->exec('GRANT USAGE, CREATE ON SCHEMA public TO ' . $quotedUser);
    $adminPdo->exec('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ' . $quotedUser);
    $adminPdo->exec('GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ' . $quotedUser);
    $adminPdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO ' . $quotedUser);
    $adminPdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO ' . $quotedUser);
}

function transferCoinTableOwnership(PDO $adminPdo, string $dbUser): void
{
    $quotedUser = quotePgIdentifier($dbUser);

    foreach (['coin_packages', 'coin_settings'] as $table) {
        $quotedTable = quotePgIdentifier($table);
        $adminPdo->exec('GRANT ALL PRIVILEGES ON TABLE ' . $quotedTable . ' TO ' . $quotedUser);
        $adminPdo->exec('ALTER TABLE ' . $quotedTable . ' OWNER TO ' . $quotedUser);
    }

    try {
        $quotedSeq = quotePgIdentifier('coin_packages_id_seq');
        $adminPdo->exec('GRANT USAGE, SELECT, UPDATE ON SEQUENCE ' . $quotedSeq . ' TO ' . $quotedUser);
        $adminPdo->exec('ALTER SEQUENCE ' . $quotedSeq . ' OWNER TO ' . $quotedUser);
    } catch (Throwable) {
        // coin_settings uses a fixed id, no sequence required.
    }
}

$dbUser = (string) ($config['db']['user'] ?? 'nexus');
$dbName = (string) ($config['db']['name'] ?? 'nexus');
$messages = [];
$errors = [];
$ready = false;
$needsAdmin = false;

try {
    $pdo = Database::connect($config['db']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin-migrate') {
        $adminUser = trim((string) ($_POST['admin_user'] ?? 'postgres'));
        $adminPass = (string) ($_POST['admin_pass'] ?? '');
        if ($adminUser === '' || $adminPass === '') {
            throw new RuntimeException('PostgreSQL admin username and password are required.');
        }

        $adminPdo = connectAdminPdo($config, $adminUser, $adminPass);
        grantCoinSchemaAccess($adminPdo, $dbUser);
        CoinPackageService::ensureSchema($adminPdo);
        transferCoinTableOwnership($adminPdo, $dbUser);
        $messages[] = 'Coin tables created using PostgreSQL admin account.';
    } else {
        CoinPackageService::ensureSchema($pdo);
    }

  $pdo = Database::connect($config['db']);
    $ready = CoinPackageService::tablesReady($pdo);
    if ($ready) {
        $messages[] = 'Coin tables are ready.';
        $messages[] = 'Packages: ' . count(CoinPackageService::listAll($pdo));
    } elseif (empty($messages)) {
        throw new RuntimeException('Coin tables could not be created with the app database user.');
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
    if (str_contains($e->getMessage(), 'permission denied') || str_contains($e->getMessage(), 'Insufficient privilege')) {
        $needsAdmin = true;
    }
}

$manualSql = <<<SQL
-- Run this in phpPgAdmin / pgAdmin as postgres (or another superuser)
GRANT USAGE, CREATE ON SCHEMA public TO {$dbUser};

CREATE TABLE IF NOT EXISTS coin_packages (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    coin_amount INTEGER NOT NULL,
    price_usd NUMERIC(10,2) NOT NULL,
    tone VARCHAR(40) NOT NULL DEFAULT 'package-blue',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coin_settings (
    id INTEGER PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    custom_recharge_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    custom_coin_price_usd NUMERIC(10,4) NOT NULL DEFAULT 0.01,
    custom_coin_min INTEGER NOT NULL DEFAULT 50,
    custom_coin_max INTEGER NOT NULL DEFAULT 100000,
    whatsapp_number VARCHAR(20) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO coin_settings (id, custom_recharge_enabled, custom_coin_price_usd, custom_coin_min, custom_coin_max, whatsapp_number)
SELECT 1, TRUE, 0.01, 50, 100000, ''
WHERE NOT EXISTS (SELECT 1 FROM coin_settings WHERE id = 1);

INSERT INTO coin_packages (name, coin_amount, price_usd, tone, sort_order, is_active)
SELECT 'Starter Pack', 100, 1.00, 'package-blue', 1, TRUE
WHERE NOT EXISTS (SELECT 1 FROM coin_packages WHERE coin_amount = 100);

INSERT INTO coin_packages (name, coin_amount, price_usd, tone, sort_order, is_active)
SELECT 'Growth Pack', 250, 2.00, 'package-purple', 2, TRUE
WHERE NOT EXISTS (SELECT 1 FROM coin_packages WHERE coin_amount = 250);

INSERT INTO coin_packages (name, coin_amount, price_usd, tone, sort_order, is_active)
SELECT 'Pro Pack', 500, 4.00, 'package-orange', 3, TRUE
WHERE NOT EXISTS (SELECT 1 FROM coin_packages WHERE coin_amount = 500);

INSERT INTO coin_packages (name, coin_amount, price_usd, tone, sort_order, is_active)
SELECT 'Enterprise Pack', 1000, 7.00, 'package-green', 4, TRUE
WHERE NOT EXISTS (SELECT 1 FROM coin_packages WHERE coin_amount = 1000);

GRANT ALL PRIVILEGES ON TABLE coin_packages TO {$dbUser};
GRANT ALL PRIVILEGES ON TABLE coin_settings TO {$dbUser};
ALTER TABLE coin_packages OWNER TO {$dbUser};
ALTER TABLE coin_settings OWNER TO {$dbUser};
GRANT USAGE, SELECT, UPDATE ON SEQUENCE coin_packages_id_seq TO {$dbUser};
ALTER SEQUENCE coin_packages_id_seq OWNER TO {$dbUser};
SQL;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Coin Migration</title>
  <style>
    body { font-family: Arial, sans-serif; background:#0b1020; color:#e5e7eb; padding:2rem; max-width:900px; margin:0 auto; }
    .ok { color:#34d399; }
    .bad { color:#f87171; }
    a { color:#22d3ee; }
    .card { background:#111827; border:1px solid #1f2937; border-radius:1rem; padding:1.25rem; margin:1rem 0; }
    input { width:100%; padding:.75rem; border-radius:.5rem; border:1px solid #374151; background:#0f172a; color:#fff; margin:.5rem 0 1rem; }
    button { background:linear-gradient(135deg,#22d3ee,#3b82f6); color:#fff; border:0; padding:.8rem 1.2rem; border-radius:.75rem; font-weight:600; cursor:pointer; }
    pre { background:#0f172a; padding:1rem; border-radius:.75rem; overflow:auto; white-space:pre-wrap; }
    label { font-size:.9rem; color:#cbd5e1; }
  </style>
</head>
<body>
  <h1>Coin Tables Migration</h1>
  <p>Database: <strong><?= htmlspecialchars($dbName) ?></strong> · App user: <strong><?= htmlspecialchars($dbUser) ?></strong></p>

  <?php foreach ($messages as $message): ?>
    <p class="ok">✓ <?= htmlspecialchars($message) ?></p>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <p class="bad">✗ <?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <?php if ($ready): ?>
    <p class="ok">Done. <a href="/nexus-admin/">Open Admin Panel</a> and delete <code>migrate-coins.php</code>.</p>
  <?php elseif ($needsAdmin): ?>
    <div class="card">
      <h2>Option 1 — Use PostgreSQL admin (recommended)</h2>
      <p>Your app user <code><?= htmlspecialchars($dbUser) ?></code> cannot create tables. Enter your server postgres password once. It is not saved.</p>
      <form method="post">
        <input type="hidden" name="action" value="admin-migrate">
        <label>Admin username</label>
        <input type="text" name="admin_user" value="postgres" required>
        <label>Admin password</label>
        <input type="password" name="admin_pass" required>
        <button type="submit">Create coin tables with admin account</button>
      </form>
    </div>

    <div class="card">
      <h2>Option 2 — Run SQL manually</h2>
      <p>Open phpPgAdmin, pgAdmin, or SSH and run this as <code>postgres</code> on database <code><?= htmlspecialchars($dbName) ?></code>:</p>
      <pre><?= htmlspecialchars($manualSql) ?></pre>
      <p>Then refresh this page.</p>
    </div>

    <div class="card">
      <h2>Option 3 — SSH one-liner</h2>
      <pre>sudo -u postgres psql -d <?= htmlspecialchars($dbName) ?> -c "GRANT USAGE, CREATE ON SCHEMA public TO <?= htmlspecialchars($dbUser) ?>;"</pre>
      <p>After granting CREATE, reload <a href="/migrate-coins.php">/migrate-coins.php</a> and it should succeed with the app user.</p>
    </div>
  <?php else: ?>
    <p>Try again or use <a href="/fix-db-permissions.php">fix-db-permissions.php</a>.</p>
  <?php endif; ?>
</body>
</html>
