<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$code = $_GET['code'] ?? '';
if ($code === '') {
    redirect(base_url($config) . '/login.html?oauthError=missing_code');
}

$tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'code' => $code,
            'client_id' => $config['google']['client_id'],
            'client_secret' => $config['google']['client_secret'],
            'redirect_uri' => $config['google']['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]),
    ],
]));

$tokenData = json_decode($tokenResponse ?: '', true);
$accessToken = $tokenData['access_token'] ?? '';
if ($accessToken === '') {
    redirect(base_url($config) . '/login.html?oauthError=token_failed');
}

$userResponse = file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false, stream_context_create([
    'http' => [
        'header' => 'Authorization: Bearer ' . $accessToken,
    ],
]));

$userData = json_decode($userResponse ?: '', true);
$email = sanitize_email($userData['email'] ?? '');
$name = trim($userData['name'] ?? '');

if ($email === '') {
    redirect(base_url($config) . '/login.html?oauthError=missing_email');
}

$user = UserService::findOrCreateGoogleUser($pdo, $email, $name);
Auth::setAppUserId((int) $user['id']);
redirect(base_url($config) . '/dashboard.html');
