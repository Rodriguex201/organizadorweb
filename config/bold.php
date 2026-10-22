<?php

return [
    'enabled' => env('BOLD_ENABLED', false),
    'environment' => env('BOLD_ENV', 'sandbox'),
    // Identity key for the Payment Button / Payment Link API. Server-side only.
    'api_key' => env('BOLD_API_KEY', ''),
    // Dedicated attempt persistence; deliberately no DB_* or portal credential fallback.
    'database' => [
        'host' => env('BOLD_DB_HOST', ''),
        'port' => env('BOLD_DB_PORT', '3306'),
        'database' => env('BOLD_DB_DATABASE', 'organizadorprueba'),
        'username' => env('BOLD_DB_USERNAME', ''),
        'password' => env('BOLD_DB_PASSWORD', ''),
    ],
    // No secret key is required by the link creation endpoint.
];
