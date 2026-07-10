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

$messages = [];
$errors = [];

try {
    $pdo = Database::connect($config['db']);
    CoinPackageService::ensureSchema($pdo);

    if (CoinPackageService::tablesReady($pdo)) {
        $messages[] = 'Coin tables are ready.';
        $messages[] = 'Packages: ' . count(CoinPackageService::listAll($pdo));
    } else {
        throw new RuntimeException('Coin tables could not be created. Check database CREATE permissions.');
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Coin Migration</title>
  <style>
    body { font-family: Arial, sans-serif; background:#0b1020; color:#e5e7eb; padding:2rem; max-width:720px; margin:0 auto; }
    .ok { color:#34d399; }
    .bad { color:#f87171; }
    a { color:#22d3ee; }
  </style>
</head>
<body>
  <h1>Coin Tables Migration</h1>

  <?php foreach ($messages as $message): ?>
    <p class="ok">✓ <?= htmlspecialchars($message) ?></p>
  <?php endforeach; ?>

  <?php foreach ($errors as $error): ?>
    <p class="bad">✗ <?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <?php if (!$errors): ?>
    <p class="ok">Done. <a href="/nexus-admin/">Open Admin Panel</a> and delete <code>migrate-coins.php</code>.</p>
  <?php else: ?>
    <p>Try <a href="/fix-db-permissions.php">fix-db-permissions.php</a> or run the SQL in database.sql manually.</p>
  <?php endif; ?>
</body>
</html>
