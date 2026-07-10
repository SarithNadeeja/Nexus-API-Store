<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/GoogleOAuthService.php';

try {
    if (!GoogleOAuthService::isConfigured($config)) {
        redirect(base_url($config) . '/login.html?oauthError=not_configured');
    }

    $state = GoogleOAuthService::createState();
    redirect(GoogleOAuthService::authUrl($config, $state));
} catch (Throwable $e) {
    error_log('Google OAuth start failed: ' . $e->getMessage());
    redirect(base_url($config) . '/login.html?oauthError=start_failed');
}
