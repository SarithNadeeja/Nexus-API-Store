<?php

declare(strict_types=1);

/**
 * Legacy bootstrap for non-public_html tooling. Prefer public_html/includes/bootstrap.php.
 */
$publicHtml = dirname(__DIR__) . '/public_html';
$configPath = $publicHtml . '/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Missing public_html/config.php. Pull latest code or copy config.sample.php to config.php.']);
    exit;
}

$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'UTC');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

$pdo = Database::connect($config['db']);
