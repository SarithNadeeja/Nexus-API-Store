<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
@ini_set('display_errors', '0');

function check_line(string $message): void
{
    echo $message . "\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

check_line('step 1: php ' . PHP_VERSION);

$includes = [
    'load-config.php',
    'Database.php',
    'helpers.php',
    'Auth.php',
    'Mailer.php',
    'UserService.php',
    'AdminService.php',
    'CoinPackageService.php',
    'KeyPool.php',
    'VerificationService.php',
    'bootstrap.php',
];

foreach ($includes as $file) {
    $path = __DIR__ . '/includes/' . $file;
    if (!is_readable($path)) {
        check_line('MISSING: includes/' . $file);
        exit;
    }
    check_line('found: includes/' . $file);
}

check_line('step 2: loading config');
require_once __DIR__ . '/includes/bootstrap.php';
check_line('step 3: bootstrap loaded');

check_line('step 4: database query');
$version = (string) $pdo->query('SELECT version()')->fetchColumn();
check_line('step 5: database ok - ' . substr($version, 0, 60));

check_line('step 6: guest session');
$session = UserService::getOptionalSession($pdo);
check_line('step 7: api ready - authenticated=' . ($session['authenticated'] ? 'yes' : 'no'));

check_line('ALL CHECKS PASSED');
