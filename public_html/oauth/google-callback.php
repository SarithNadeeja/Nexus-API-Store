<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/GoogleOAuthService.php';

$base = base_url($config);

if (!empty($_GET['error'])) {
    $error = $_GET['error'] === 'access_denied' ? 'access_denied' : 'oauth_denied';
    redirect($base . '/login.html?oauthError=' . urlencode($error));
}

$code = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');

if ($code === '') {
    redirect($base . '/login.html?oauthError=missing_code');
}

try {
    if (!GoogleOAuthService::isConfigured($config)) {
        redirect($base . '/login.html?oauthError=not_configured');
    }

    GoogleOAuthService::validateState($state);
    $tokenData = GoogleOAuthService::exchangeCode($config, $code);
    $userData = GoogleOAuthService::fetchUserInfo((string) $tokenData['access_token']);

    $email = sanitize_email((string) ($userData['email'] ?? ''));
    $name = trim((string) ($userData['name'] ?? ''));

    if ($email === '') {
        redirect($base . '/login.html?oauthError=missing_email');
    }

    $user = UserService::findOrCreateGoogleUser($pdo, $email, $name);
    Auth::setAppUserId((int) $user['id']);
    redirect($base . '/dashboard.html');
} catch (Throwable $e) {
    error_log('Google OAuth callback failed: ' . $e->getMessage());
    redirect($base . '/login.html?oauthError=token_failed');
}
