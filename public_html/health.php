<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$checks = [];
$ok = true;

function health_check(array &$checks, string $name, callable $fn): void
{
    global $ok;
    try {
        $result = $fn();
        $checks[$name] = is_array($result) ? array_merge(['status' => 'ok'], $result) : ['status' => 'ok'];
    } catch (Throwable $e) {
        $ok = false;
        $checks[$name] = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }
}

health_check($checks, 'php', static function () {
    return [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
    ];
});

health_check($checks, 'config', static function () {
    require_once __DIR__ . '/includes/ConfigLoader.php';
    $config = load_app_config(__DIR__);

    return [
        'app' => $config['app_name'] ?? 'unknown',
        'baseUrl' => $config['base_url'] ?? '',
        'hasLocalConfig' => file_exists(__DIR__ . '/config.local.php'),
    ];
});

health_check($checks, 'includes', static function () {
    $files = [
        'Database.php',
        'helpers.php',
        'Auth.php',
        'UserService.php',
        'AdminService.php',
        'CoinPackageService.php',
        'ApiKeyPoolService.php',
        'GoogleOAuthService.php',
        'VerificationService.php',
    ];

    foreach ($files as $file) {
        $path = __DIR__ . '/includes/' . $file;
        if (!is_readable($path)) {
            throw new RuntimeException('Missing include file: ' . $file);
        }
        require_once $path;
    }

    return ['files' => count($files)];
});

health_check($checks, 'database', static function () {
    require_once __DIR__ . '/includes/ConfigLoader.php';
    require_once __DIR__ . '/includes/Database.php';
    $config = load_app_config(__DIR__);
    $pdo = Database::connect($config['db']);
    $version = (string) $pdo->query('SELECT version()')->fetchColumn();

    return ['version' => $version];
});

health_check($checks, 'tables', static function () {
    require_once __DIR__ . '/includes/ConfigLoader.php';
    require_once __DIR__ . '/includes/Database.php';
    $config = load_app_config(__DIR__);
    $pdo = Database::connect($config['db']);
    $required = ['categories', 'api_listings', 'app_users', 'admin_users', 'api_key_inventory', 'api_purchases'];
    $missing = [];

    foreach ($required as $table) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables
             WHERE table_schema = current_schema() AND table_name = ?'
        );
        $stmt->execute([$table]);
        if (!$stmt->fetchColumn()) {
            $missing[] = $table;
        }
    }

    if ($missing) {
        throw new RuntimeException('Missing tables: ' . implode(', ', $missing));
    }

    return ['tables' => count($required)];
});

http_response_code($ok ? 200 : 500);
echo json_encode([
    'ok' => $ok,
    'checks' => $checks,
    'time' => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
