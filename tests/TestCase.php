<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Tests require APP_ENV=testing and the isolated in-memory SQLite database. Clear cached configuration before running tests.');
        }

        config([
            'eduworld.turnstile_site_key' => null,
            'eduworld.turnstile_secret_key' => null,
        ]);

        return $app;
    }
}
