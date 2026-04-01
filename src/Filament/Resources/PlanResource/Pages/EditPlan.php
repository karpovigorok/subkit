<?php

namespace SubKit\Filament\Resources\PlanResource\Pages;

use SubKit\Filament\Resources\PlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => \Laravel\Cashier\Subscription::whereIn(
                    'stripe_price',
                    $this->record->providerPrices()->pluck('provider_price_id')
                )->doesntExist()),
        ];
    }
}
