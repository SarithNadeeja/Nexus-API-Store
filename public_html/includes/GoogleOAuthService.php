<?php

declare(strict_types=1);

final class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const STATE_SESSION_KEY = 'google_oauth_state';

    public static function settings(array $config): array
    {
        $google = $config['google'] ?? [];
        $base = rtrim((string) ($config['base_url'] ?? ''), '/');

        if ($base !== '' && str_starts_with($base, 'http://')) {
            $host = parse_url($base, PHP_URL_HOST) ?: '';
            if ($host !== 'localhost' && $host !== '127.0.0.1') {
                $base = 'https://' . substr($base, 7);
            }
        }

        if ($base === '' && !empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        $redirectUri = trim((string) ($google['redirect_uri'] ?? ''));
        if ($redirectUri === '') {
            $redirectUri = $base . '/oauth/google-callback.php';
        }

        return [
            'client_id' => trim((string) ($google['client_id'] ?? '')),
            'client_secret' => trim((string) ($google['client_secret'] ?? '')),
            'redirect_uri' => $redirectUri,
        ];
    }

    public static function isConfigured(array $config): bool
    {
        $settings = self::settings($config);

        return $settings['client_id'] !== ''
            && !str_contains($settings['client_id'], 'your-google-client-id')
            && $settings['client_secret'] !== ''
            && !str_contains($settings['client_secret'], 'your-google-client-secret');
    }

    public static function ensureConfigured(array $config): void
    {
        if (!self::isConfigured($config)) {
            throw new RuntimeException('Google sign-in is not configured yet. Add your OAuth client secret in config.php.');
        }
    }

    public static function createState(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION[self::STATE_SESSION_KEY] = $state;

        return $state;
    }

    public static function validateState(?string $state): void
    {
        $expected = $_SESSION[self::STATE_SESSION_KEY] ?? '';
        unset($_SESSION[self::STATE_SESSION_KEY]);

        if ($expected === '' || $state === null || $state === '' || !hash_equals($expected, $state)) {
            throw new RuntimeException('Google sign-in expired. Please try again.');
        }
    }

    public static function authUrl(array $config, string $state): string
    {
        $settings = self::settings($config);

        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $settings['client_id'],
            'redirect_uri' => $settings['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
            'state' => $state,
        ]);
    }

    public static function exchangeCode(array $config, string $code): array
    {
        $settings = self::settings($config);
        $response = self::request('POST', self::TOKEN_URL, [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'code' => $code,
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => $settings['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]));

        if (empty($response['access_token'])) {
            $message = (string) ($response['error_description'] ?? $response['error'] ?? 'Token exchange failed.');
            throw new RuntimeException($message);
        }

        return $response;
    }

    public static function fetchUserInfo(string $accessToken): array
    {
        $response = self::request('GET', self::USERINFO_URL, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);

        if (empty($response['email'])) {
            throw new RuntimeException('Google did not return an email address for this account.');
        }

        return $response;
    }

    public static function oauthErrorMessage(?string $code): string
    {
        switch ($code) {
            case 'missing_code':
                return 'Google sign-in was cancelled or did not return an authorization code.';
            case 'token_failed':
                return 'Google token exchange failed. Check your OAuth client ID, secret, and redirect URI.';
            case 'missing_email':
                return 'Google did not provide an email address for this account.';
            case 'not_configured':
                return 'Google sign-in is not configured on the server yet.';
            case 'access_denied':
                return 'Google sign-in was cancelled.';
            default:
                if ($code) {
                    return 'Google sign-in failed: ' . str_replace('_', ' ', $code);
                }
                return 'Google sign-in failed. Please try again.';
        }
    }

    private static function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                throw new RuntimeException('Google request failed: ' . $error);
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid response from Google (HTTP ' . $status . ').');
            }

            if ($status >= 400) {
                $message = (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'Google request failed.');
                throw new RuntimeException($message);
            }

            return $decoded;
        }

        $headerLines = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headerLines,
                'content' => $body ?? '',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $raw = file_get_contents($url, false, $context);
        $decoded = json_decode($raw ?: '', true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid response from Google.');
        }

        return $decoded;
    }
}
