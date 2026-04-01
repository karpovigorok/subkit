<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', \App\Models\User::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Cashier\CashierServiceProvider::class,
            \SubKit\SubKitServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $cashierMigrations = __DIR__ . '/../vendor/laravel/cashier/database/migrations';
        if (is_dir($cashierMigrations)) {
            $this->loadMigrationsFrom($cashierMigrations);
        }
    }
}
