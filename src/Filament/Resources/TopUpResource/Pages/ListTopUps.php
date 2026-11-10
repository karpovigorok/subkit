<?php

namespace SubKit\Filament\Resources\TopUpResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use SubKit\Filament\Resources\TopUpResource;

class ListTopUps extends ListRecords
{
    protected static string $resource = TopUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
