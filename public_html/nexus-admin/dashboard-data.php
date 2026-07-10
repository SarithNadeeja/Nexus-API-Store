<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/DashboardService.php';

AdminService::ensureDefaultAdmin($pdo);
Auth::requireAdmin($pdo);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(DashboardService::getData($pdo), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
