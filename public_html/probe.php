<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

function probe_line(string $message): void
{
    echo $message . "\n";
    flush();
}

probe_line('php: ' . PHP_VERSION);

$files = [
    'Database.php',
    'helpers.php',
    'Auth.php',
    'Mailer.php',
    'UserService.php',
    'AdminService.php',
    'CoinPackageService.php',
    'KeyPool.php',
    'VerificationService.php',
];

foreach ($files as $file) {
    $path = __DIR__ . '/includes/' . $file;
    if (!is_readable($path)) {
        probe_line($file . ': MISSING');
        exit;
    }

    probe_line($file . ': loading...');
    try {
        require_once $path;
        probe_line($file . ': OK');
    } catch (Throwable $e) {
        probe_line($file . ': FAIL - ' . $e->getMessage());
        exit;
    }
}

probe_line('bootstrap: loading...');
try {
    require_once __DIR__ . '/includes/bootstrap.php';
    probe_line('bootstrap: OK');
    probe_line('database: ' . substr((string) $pdo->query('SELECT 1')->fetchColumn(), 0, 10));
} catch (Throwable $e) {
    probe_line('bootstrap: FAIL - ' . $e->getMessage());
}

probe_line('done');
