<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

trait SafeRefreshDatabase
{
    use RefreshDatabase {
        refreshDatabase as baseRefreshDatabase;
    }

    protected function refreshDatabase()
    {
        $this->assertTestingDatabaseConnection();
        $this->baseRefreshDatabase();
    }

    private function assertTestingDatabaseConnection(): void
    {
        $env = app()->environment();
        $default = config('database.default');
        $connection = config("database.connections.$default", []);
        $driver = $connection['driver'] ?? null;
        $database = $connection['database'] ?? null;

        $isTestingEnv = in_array($env, ['testing', 'test'], true);
        $dbLabel = is_string($database) ? $database : (string) $database;
        $dbName = is_string($database) ? basename($database) : '';

        $isSqliteMemory = $driver === 'sqlite' && in_array($database, [':memory:', null], true);
        $isTestingName = $dbName !== '' && preg_match('/(^|_)test(ing)?($|_)/i', $dbName);

        if (! $isTestingEnv || (! $isSqliteMemory && ! $isTestingName)) {
            throw new RuntimeException(
                "Refusing to refresh the database outside a test database. env={$env}, driver={$driver}, database={$dbLabel}."
            );
        }
    }
}
