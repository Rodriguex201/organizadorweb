<?php

return [
    // Explicit opt-in; never inherit the administrative DB credentials.
    'enabled' => env('PORTAL_REVIEW_ENABLED', false),
    'host' => env('PORTAL_READ_HOST', '168.232.32.74'),
    'port' => env('PORTAL_READ_PORT', '3306'),
    'database' => env('PORTAL_READ_DATABASE', 'organizadorprueba'),
    'username' => env('PORTAL_READ_USERNAME', 'RmSoft20X'),
    'password' => env('PORTAL_READ_PASSWORD'),
];
