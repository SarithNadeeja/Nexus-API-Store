<?php
/**
 * Copy this file to config.php and fill in your values.
 */
return [
    'app_name' => 'Nexus API Store',
    'base_url' => 'https://nexusapistore.com',
    'timezone' => 'UTC',

    'db' => [
        'driver' => 'pgsql',
        'host' => 'localhost',
        'port' => 5432,
        'name' => 'nexus',
        'user' => 'nexus',
        'pass' => 'your_database_password',
    ],

    'mail' => [
        'from_email' => 'nexusapistore@gmail.com',
        'from_name' => 'Nexus API Store',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => 'nexusapistore@gmail.com',
        'smtp_pass' => 'your-gmail-app-password',
    ],

    'google' => [
        'client_id' => 'your-google-client-id.apps.googleusercontent.com',
        'client_secret' => 'your-google-client-secret',
        'redirect_uri' => 'https://nexusapistore.com/oauth/google-callback.php',
    ],

    'session' => [
        'name' => 'NEXUSSESSID',
        'lifetime' => 86400,
    ],
];
