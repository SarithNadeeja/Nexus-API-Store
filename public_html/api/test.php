<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'authenticated' => UserService::getOptionalSession($pdo)['authenticated'] ?? false,
    'time' => date('c'),
], JSON_UNESCAPED_SLASHES);
