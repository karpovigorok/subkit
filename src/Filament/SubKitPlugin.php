<?php

namespace SubKit\Filament;

use SubKit\Filament\Resources\FeatureResource;
use SubKit\Filament\Resources\PlanResource;
use SubKit\Filament\Resources\PlanSetResource;
use SubKit\Filament\Resources\SubscriberResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class SubKitPlugin implements Plugin
{
    public function getId(): string
    {
        return 'subkit';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            SubscriberResource::class,
            PlanResource::class,
            PlanSetResource::class,
            FeatureResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
