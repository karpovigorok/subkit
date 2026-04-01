<?php

namespace SubKit\Filament\Resources\PlanSetResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use SubKit\Filament\Resources\PlanSetResource;

class ListPlanSets extends ListRecords
{
    protected static string $resource = PlanSetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
