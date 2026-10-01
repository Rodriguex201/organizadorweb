<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\SqliteMemoryConnectionFactory;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        if (getenv('APP_ENV') !== 'testing'
            || getenv('DB_CONNECTION') !== 'sqlite'
            || getenv('DB_DATABASE') !== ':memory:') {
            throw new \RuntimeException('Use tests/bootstrap.php with SQLite :memory: before booting tests.');
        }

        $app = require __DIR__.'/../bootstrap/app.php';
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));

        // Runs before providers boot and before database-testing traits can run.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app): void {
            $connection = $app['config']->get('database.default');
            $config = $app['config']->get("database.connections.{$connection}", []);
            if ($app->configurationIsCached()
                || ($config['driver'] ?? null) !== 'sqlite'
                || ($config['database'] ?? null) !== ':memory:'
                || !empty($config['url'])
                || isset($config['read'])
                || isset($config['write'])) {
                throw new \RuntimeException('Aborting tests: configuration must be uncached SQLite :memory:.');
            }
        });

        $app->extend('db.factory', fn ($factory, $app) => new SqliteMemoryConnectionFactory($app));
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        if (config("database.connections.{$connection}.driver") !== 'sqlite'
            || config("database.connections.{$connection}.database") !== ':memory:') {
            throw new \RuntimeException('Tests require an isolated SQLite in-memory database.');
        }
    }
}
