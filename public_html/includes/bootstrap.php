<?php

declare(strict_types=1);

if (!function_exists('load_app_config')) {
    function load_app_config(string $publicHtmlDir): array
    {
        $configPath = $publicHtmlDir . '/config.php';
        $samplePath = $publicHtmlDir . '/config.sample.php';
        $localPath = $publicHtmlDir . '/config.local.php';

        if (is_readable($configPath)) {
            $config = require $configPath;
        } elseif (is_readable($samplePath)) {
            $config = require $samplePath;
            if (is_readable($localPath)) {
                $localConfig = require $localPath;
                if (is_array($localConfig)) {
                    $config = array_replace_recursive($config, $localConfig);
                }
            }
        } else {
            throw new RuntimeException('Missing configuration. Add config.php or config.sample.php on the server.');
        }

        if (!is_array($config)) {
            throw new RuntimeException('Invalid configuration file.');
        }

        return $config;
    }
}

if (!function_exists('bootstrap_require')) {
    function bootstrap_require(string $file): void
    {
        $path = __DIR__ . '/' . $file;
        if (!is_readable($path)) {
            throw new RuntimeException('Missing required server file: includes/' . $file);
        }

        require_once $path;
    }
}

try {
    $config = load_app_config(dirname(__DIR__));
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => $e->getMessage()]);
    exit;
}

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

try {
    bootstrap_require('Database.php');
    bootstrap_require('helpers.php');
    bootstrap_require('Auth.php');
    bootstrap_require('Mailer.php');
    bootstrap_require('UserService.php');
    bootstrap_require('AdminService.php');
    bootstrap_require('CoinPackageService.php');
    bootstrap_require('ApiKeyPoolService.php');
    bootstrap_require('VerificationService.php');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => $e->getMessage()]);
    exit;
}

try {
    $pdo = Database::connect($config['db']);
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Database connection failed. Check config.php and database service.']);
    exit;
}

try {
    CoinPackageService::ensureSchema($pdo);
} catch (Throwable $e) {
    error_log('Coin schema initialization failed: ' . $e->getMessage());
}

try {
    ApiKeyPoolService::ensureSchema($pdo);
} catch (Throwable $e) {
    error_log('API key pool schema initialization failed: ' . $e->getMessage());
}
