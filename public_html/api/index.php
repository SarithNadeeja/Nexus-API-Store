<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Access-Control-Allow-Origin: ' . base_url($config));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$path = rtrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($path === '/api/public/apis' && $method === 'GET') {
        json_response(UserService::getPublicApis($pdo));
    } elseif ($path === '/api/public/coin-packages' && $method === 'GET') {
        json_response(CoinPackageService::getPublicCatalog($pdo));
    } elseif ($path === '/api/public/categories' && $method === 'GET') {
        json_response(UserService::getPublicCategories($pdo));
    } elseif ($path === '/api/auth/register' && $method === 'POST') {
        $body = read_json_body();
        json_response(UserService::register($pdo, $config, $body));
    } elseif ($path === '/api/auth/resend-verification' && $method === 'POST') {
        $body = read_json_body();
        require_fields($body, ['email']);
        UserService::resendVerification($pdo, $config, $body['email']);
        json_response(['success' => true, 'message' => 'Verification email sent. Please check your inbox and spam folder.']);
    } elseif ($path === '/api/auth/login' && $method === 'POST') {
        $body = read_json_body();
        require_fields($body, ['email', 'password']);
        json_response(UserService::login($pdo, $body));
    } elseif ($path === '/api/auth/logout' && $method === 'POST') {
        UserService::logout();
        json_response(['success' => true]);
    } elseif ($path === '/api/auth/me' && $method === 'GET') {
        json_response(UserService::getOptionalSession($pdo));
    } elseif (str_starts_with($path, '/api/auth/verify') && $method === 'GET') {
        $token = $_GET['token'] ?? '';
        if ($token === '') {
            json_error('Verification token is required.');
        }
        json_response(UserService::verifyEmail($pdo, $token));
    } elseif ($path === '/api/user/recharge' && $method === 'POST') {
        $user = Auth::requireVerifiedAppUser($pdo);
        $body = read_json_body();
        $coins = (int) ($body['coins'] ?? 0);
        $packageId = isset($body['packageId']) ? (int) $body['packageId'] : null;
        $custom = !empty($body['custom']);
        json_response(UserService::recharge($pdo, $user, $coins, $packageId ?: null, $custom));
    } elseif ($path === '/api/user/purchases' && $method === 'GET') {
        $user = Auth::requireVerifiedAppUser($pdo);
        json_response(UserService::getUserPurchases($pdo, $user));
    } elseif ($path === '/api/user/purchases' && $method === 'POST') {
        $user = Auth::requireVerifiedAppUser($pdo);
        $body = read_json_body();
        $apiId = (int) ($body['apiId'] ?? 0);
        if ($apiId <= 0) {
            json_error('API id is required.');
        }
        json_response(UserService::purchase($pdo, $user, $apiId));
    } else {
        json_error('Endpoint not found.', 404);
    }
} catch (InvalidArgumentException $e) {
    $status = str_contains($e->getMessage(), 'sign in') ? 401 : 400;
    json_error($e->getMessage(), $status);
} catch (PDOException $e) {
    $message = $e->getMessage();
    if (str_contains($message, 'permission denied')) {
        json_error('Database permission error. Run /fix-db-permissions.php once, then delete it.', 503);
    }
    json_error('Database error: ' . $message, 503);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 503);
}
