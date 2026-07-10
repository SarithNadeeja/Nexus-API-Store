<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Missing config.php. Copy config.sample.php to config.php and configure it.']);
    exit;
}

$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session']['name'] ?? 'NEXUSSESSID');
    session_set_cookie_params([
        'lifetime' => $config['session']['lifetime'] ?? 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/AdminService.php';
require_once __DIR__ . '/CoinPackageService.php';
require_once __DIR__ . '/VerificationService.php';

try {
    $pdo = Database::connect($config['db']);
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Database connection failed. Check config.php and database service.']);
    exit;
}

try {
    CoinPackageService::ensureSchema($pdo);
} catch (Throwable $e) {
    error_log('Coin schema initialization failed: ' . $e->getMessage());
}
