<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$path = __DIR__ . '/includes/KeyPool.php';

if (!is_readable($path)) {
    echo "MISSING: includes/KeyPool.php\n";
    exit;
}

if (function_exists('opcache_invalidate')) {
    opcache_invalidate($path, true);
}

$bytes = (string) file_get_contents($path);
echo "file: includes/KeyPool.php\n";
echo "size: " . strlen($bytes) . " bytes\n";
echo "sha256: " . hash('sha256', $bytes) . "\n";

echo "\nloading class...\n";
try {
    require_once $path;
    echo "class loaded OK\n";
    echo "POOL_MARKER=" . KeyPool::POOL_MARKER . "\n";
    echo "alias=" . (class_exists('ApiKeyPoolService', false) ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
