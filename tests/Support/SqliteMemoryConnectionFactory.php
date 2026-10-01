<?php

namespace Tests\Support;

use Illuminate\Database\Connectors\ConnectionFactory;
use RuntimeException;

/**
 * Reject unsafe connections before Laravel creates a connector or PDO resolver.
 * Applies to named connections and connections added after test setup as well.
 */
class SqliteMemoryConnectionFactory extends ConnectionFactory
{
    public function make(array $config, $name = null)
    {
        if (($config['driver'] ?? null) !== 'sqlite'
            || ($config['database'] ?? null) !== ':memory:'
            || !empty($config['url'])
            || isset($config['read'])
            || isset($config['write'])) {
            throw new RuntimeException('Tests may only connect to SQLite :memory:.');
        }

        return parent::make($config, $name);
    }
}
