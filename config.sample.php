<?php
/**
 * Copy this file to config.php and fill in your cPanel values.
 */
return [
    'app_name' => 'Nexus API Store',
    'base_url' => 'https://nexusapistore.com',
    'timezone' => 'UTC',

    'db' => [
        'host' => 'localhost',
        'name' => 'your_database_name',
        'user' => 'your_database_user',
        'pass' => 'your_database_password',
        'charset' => 'utf8mb4',
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
