<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$results = [];

function probe_step(string $label, callable $fn): void
{
    global $results;
    try {
        $detail = $fn();
        $results[] = $label . ': OK' . ($detail ? ' (' . $detail . ')' : '');
    } catch (Throwable $e) {
        $results[] = $label . ': FAIL - ' . $e->getMessage();
    }
}

probe_step('php', static fn () => PHP_VERSION);

probe_step('load-config.php', static function () {
    require_once __DIR__ . '/includes/load-config.php';
    return 'loaded';
});

probe_step('config', static function () {
    $config = load_app_config(__DIR__);
    return $config['app_name'] ?? 'app';
});

probe_step('Database.php', static function () {
    require_once __DIR__ . '/includes/Database.php';
    return 'loaded';
});

probe_step('helpers.php', static function () {
    require_once __DIR__ . '/includes/helpers.php';
    return 'loaded';
});

probe_step('Auth.php', static function () {
    require_once __DIR__ . '/includes/Auth.php';
    return 'loaded';
});

probe_step('Mailer.php', static function () {
    require_once __DIR__ . '/includes/Mailer.php';
    return 'loaded';
});

probe_step('UserService.php', static function () {
    require_once __DIR__ . '/includes/UserService.php';
    return 'loaded';
});

probe_step('AdminService.php', static function () {
    require_once __DIR__ . '/includes/AdminService.php';
    return 'loaded';
});

probe_step('CoinPackageService.php', static function () {
    require_once __DIR__ . '/includes/CoinPackageService.php';
    return 'loaded';
});

probe_step('ApiKeyPoolService.php', static function () {
    require_once __DIR__ . '/includes/ApiKeyPoolService.php';
    return 'loaded';
});

probe_step('VerificationService.php', static function () {
    require_once __DIR__ . '/includes/VerificationService.php';
    return 'loaded';
});

probe_step('GoogleOAuthService.php', static function () {
    require_once __DIR__ . '/includes/GoogleOAuthService.php';
    return 'loaded';
});

probe_step('database connect', static function () {
    require_once __DIR__ . '/includes/load-config.php';
    require_once __DIR__ . '/includes/Database.php';
    $config = load_app_config(__DIR__);
    $pdo = Database::connect($config['db']);
    return substr((string) $pdo->query('SELECT version()')->fetchColumn(), 0, 40);
});

probe_step('bootstrap.php', static function () {
    require_once __DIR__ . '/includes/bootstrap.php';
    return 'loaded';
});

echo implode("\n", $results) . "\n";
