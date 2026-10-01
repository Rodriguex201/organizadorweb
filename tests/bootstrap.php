<?php

// Tests must never load the application's cached production configuration.
$testConfigCache = __DIR__.'/config-cache-disabled.php';
if (is_file($testConfigCache)) {
    throw new RuntimeException('Remove the unexpected test config cache before running tests.');
}

foreach ([
    'APP_CONFIG_CACHE' => $testConfigCache,
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

require __DIR__.'/../vendor/autoload.php';
