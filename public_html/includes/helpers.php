<?php

declare(strict_types=1);

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['message' => $message], $status);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            json_error(ucfirst(str_replace('_', ' ', $field)) . ' is required.');
        }
    }
}

function sanitize_email(string $email): string
{
    return strtolower(trim($email));
}

function base_url(array $config): string
{
    return rtrim($config['base_url'], '/');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $message = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $message;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function db_bool($value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
}

function format_admin_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    $date = date_create($datetime);
    return $date ? $date->format('d M Y') : '—';
}

function format_admin_datetime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    $date = date_create($datetime);
    return $date ? $date->format('d M Y H:i:s') : '—';
}

function category_visual(int $index): array
{
    $tones = ['tone-purple', 'tone-green', 'tone-amber', 'tone-rose', 'tone-teal'];
    $icons = ['{ }', '🛒', '💰', '💬', '📍'];

    return [
        'tone' => $tones[$index % count($tones)],
        'icon' => $icons[$index % count($icons)],
    ];
}

function user_avatar_tone(int $index): string
{
    $tones = ['tone-blue', 'tone-purple', 'tone-green', 'tone-amber', 'tone-rose'];

    return $tones[$index % count($tones)];
}

function format_lkr($amount, int $decimals = 2): string
{
    return 'LKR ' . number_format((float) $amount, $decimals, '.', ',');
}
