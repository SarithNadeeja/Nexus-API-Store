<?php

declare(strict_types=1);

/**
 * Safe config entrypoint for git. Real secrets go in config.local.php (not committed).
 */
$config = require __DIR__ . '/config.sample.php';

$localPath = __DIR__ . '/config.local.php';
if (file_exists($localPath)) {
    $local = require $localPath;
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;
