<?php

return [
    'paths' => ['*'],                // Allow all paths
    'allowed_methods' => ['*'],      // Allow all HTTP methods
    'allowed_origins' => [
        '*'   // Add your production URL
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'X-CSRF-TOKEN'
    ],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,   // Required for cookies/auth
];
