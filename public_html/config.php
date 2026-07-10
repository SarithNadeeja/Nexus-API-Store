<?php
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
        'pass' => 'Nexus@my.com123',
    ],
    'mail' => [
        'from_email' => 'nexusapistore@gmail.com',
        'from_name' => 'Nexus API Store',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => 'nexusapistore@gmail.com',
        'smtp_pass' => 'cqxwukteqruqvhmb',
    ],
    'google' => [
        'client_id' => '535856044931-qlqkjc7v8bfh4sgv3gm5c7f6abbqnpa0.apps.googleusercontent.com',
        'client_secret' => 'your-google-client-secret',
        'redirect_uri' => 'https://nexusapistore.com/oauth/google-callback.php',
    ],
    'session' => [
        'name' => 'NEXUSSESSID',
        'lifetime' => 86400,
    ],
];
