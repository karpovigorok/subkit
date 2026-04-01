<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\CashierServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use SubKit\SubKitServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashierServiceProvider::class,
            SubKitServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $cashierMigrations = __DIR__.'/../vendor/laravel/cashier/database/migrations';
        if (is_dir($cashierMigrations)) {
            $this->loadMigrationsFrom($cashierMigrations);
        }
    }
}
