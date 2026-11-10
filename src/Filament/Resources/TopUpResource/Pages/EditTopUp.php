<?php

namespace SubKit\Filament\Resources\TopUpResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use SubKit\Filament\Resources\TopUpResource;

class EditTopUp extends EditRecord
{
    protected static string $resource = TopUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
