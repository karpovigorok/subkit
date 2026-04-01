<?php

namespace SubKit;

use SubKit\Listeners\WebhookEventDispatcher;
use SubKit\Providers\Stripe\StripeProvider;
use SubKit\Services\ProviderRegistry;
use SubKit\Services\SubscriptionService;
use SubKit\View\Components\PlanCard;
use SubKit\View\Components\PricingTable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;

class SubKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/subkit.php',
            'subkit'
        );

        $this->app->singleton(ProviderRegistry::class, function ($app) {
            $registry = new ProviderRegistry();

            foreach (config('subkit.providers', []) as $name => $class) {
                $registry->register($app->make($class));
            }

            return $registry;
        });

        $this->app->singleton(SubscriptionService::class, function ($app) {
            return new SubscriptionService(
                $app->make(ProviderRegistry::class),
            );
        });

        $this->app->alias(SubscriptionService::class, 'subkit');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/subkit.php' => config_path('subkit.php'),
            ], 'subkit-config');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            if ($this->app->runningInConsole()) {
                $cashierPath = base_path('vendor/laravel/cashier/database/migrations');
                if (file_exists($cashierPath)) {
                    $this->loadMigrationsFrom($cashierPath);
                }
            }

            if ($this->app->runningInConsole()) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/' => database_path('migrations'),
                ], 'subkit-migrations');
            }

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/subkit'),
            ], 'subkit-views');
        }

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/subkit'),
        ], 'subkit-lang');

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'subkit');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/subscription.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'subkit');

        Blade::componentNamespace('SubKit\\View\\Components', 'subkit');

        Event::listen(WebhookHandled::class, WebhookEventDispatcher::class);
    }
}
