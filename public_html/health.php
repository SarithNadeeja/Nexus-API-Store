<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'phpVersion' => PHP_VERSION,
    'steps' => [],
];

function step(array &$checks, string $name, callable $fn): void
{
    try {
        $fn();
        $checks['steps'][$name] = 'ok';
    } catch (Throwable $e) {
        $checks['steps'][$name] = $e->getMessage();
    }
}

step($checks, 'config', function (): void {
    if (!file_exists(__DIR__ . '/config.php')) {
        throw new RuntimeException('config.php missing');
    }
});

step($checks, 'database.php', function (): void {
    require_once __DIR__ . '/includes/Database.php';
});

step($checks, 'helpers.php', function (): void {
    require_once __DIR__ . '/includes/helpers.php';
});

step($checks, 'auth.php', function (): void {
    require_once __DIR__ . '/includes/Auth.php';
});

step($checks, 'mailer.php', function (): void {
    require_once __DIR__ . '/includes/Mailer.php';
});

step($checks, 'user_service.php', function (): void {
    require_once __DIR__ . '/includes/UserService.php';
});

step($checks, 'admin_service.php', function (): void {
    require_once __DIR__ . '/includes/AdminService.php';
});

step($checks, 'coin_package_service.php', function (): void {
    require_once __DIR__ . '/includes/CoinPackageService.php';
});

step($checks, 'verification_service.php', function (): void {
    require_once __DIR__ . '/includes/VerificationService.php';
});

step($checks, 'db_connect', function (): void {
    $config = require __DIR__ . '/config.php';
    $pdo = Database::connect($config['db']);
    $pdo->query('SELECT 1');
});

step($checks, 'session_start', function (): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
});

step($checks, 'coin_schema', function (): void {
    $config = require __DIR__ . '/config.php';
    $pdo = Database::connect($config['db']);
    CoinPackageService::ensureSchema($pdo);
    $pdo->query('SELECT 1 FROM coin_packages LIMIT 1');
});

$failed = array_filter($checks['steps'], static function (string $value): bool {
    return $value !== 'ok';
});
$checks['ok'] = $failed === [];

http_response_code($checks['ok'] ? 200 : 500);
echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
