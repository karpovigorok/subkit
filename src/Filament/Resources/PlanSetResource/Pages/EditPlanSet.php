<?php

namespace SubKit\Filament\Resources\PlanSetResource\Pages;

use SubKit\Filament\Resources\PlanSetResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlanSet extends EditRecord
{
    protected static string $resource = PlanSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('subkit.plan-set.preview', $this->record->code))
                ->openUrlInNewTab(),

            DeleteAction::make()
                ->visible(fn (): bool => $this->record->items()->doesntExist()),
        ];
    }
}
