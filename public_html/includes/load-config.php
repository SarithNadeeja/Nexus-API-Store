<?php

declare(strict_types=1);

if (!function_exists('load_app_config')) {
    function load_app_config(string $publicHtmlDir): array
    {
        $configPath = $publicHtmlDir . '/config.php';
        $samplePath = $publicHtmlDir . '/config.sample.php';
        $localPath = $publicHtmlDir . '/config.local.php';

        if (is_readable($configPath)) {
            $config = require $configPath;
        } elseif (is_readable($samplePath)) {
            $config = require $samplePath;
            if (is_readable($localPath)) {
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
}
