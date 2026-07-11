<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$path = __DIR__ . '/includes/ApiKeyPoolService.php';

if (!is_readable($path)) {
    echo "MISSING: includes/ApiKeyPoolService.php\n";
    exit;
}

$bytes = (string) file_get_contents($path);
$size = strlen($bytes);
$sha = hash('sha256', $bytes);
$lines = substr_count($bytes, "\n") + 1;

echo "file: includes/ApiKeyPoolService.php\n";
echo "size: {$size} bytes\n";
echo "lines: {$lines}\n";
echo "sha256: {$sha}\n";
echo "starts_with: " . substr($bytes, 0, 40) . "\n";
echo "ends_with: " . substr($bytes, -40) . "\n";

echo "\nloading class...\n";
require_once $path;
echo "class loaded OK\n";
echo "POOL_MARKER=" . ApiKeyPoolService::POOL_MARKER . "\n";
