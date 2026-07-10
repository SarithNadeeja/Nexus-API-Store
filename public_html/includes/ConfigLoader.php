<?php

declare(strict_types=1);

function load_app_config(string $publicHtmlDir): array
{
    $configPath = $publicHtmlDir . '/config.php';
    $samplePath = $publicHtmlDir . '/config.sample.php';
    $localPath = $publicHtmlDir . '/config.local.php';

    if (file_exists($configPath)) {
        $config = require $configPath;
    } elseif (file_exists($samplePath)) {
        $config = require $samplePath;
        if (file_exists($localPath)) {
            $localConfig = require $localPath;
            if (is_array($localConfig)) {
                $config = array_replace_recursive($config, $localConfig);
            }
        }
    } else {
        throw new RuntimeException('Missing configuration. Add config.php or config.sample.php on the server.');
    }

    if (!is_array($config)) {
        throw new RuntimeException('Invalid configuration file.');
    }

    return $config;
}
