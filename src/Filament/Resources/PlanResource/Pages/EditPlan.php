<?php

namespace SubKit\Filament\Resources\PlanResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Laravel\Cashier\Subscription;
use SubKit\Filament\Resources\PlanResource;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => Subscription::whereIn(
                    'stripe_price',
                    $this->record->providerPrices()->pluck('provider_price_id')
                )->doesntExist()),
        ];
    }
}
