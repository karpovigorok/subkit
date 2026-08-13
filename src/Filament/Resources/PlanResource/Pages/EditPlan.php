<?php

namespace SubKit\Filament\Resources\PlanResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Laravel\Cashier\Subscription;
use SubKit\Filament\Resources\PlanResource;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    private array $pendingAssigneeIds = [];

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingAssigneeIds = array_map('strval', $data['assignee_ids'] ?? []);
        unset($data['assignee_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncAssignments($this->pendingAssigneeIds);
    }

    private function syncAssignments(array $ids): void
    {
        $model = config('subkit.billable_model');
        if (! $model || ! $this->record->is_private) {
            return;
        }

        $existing = $this->record->assignments()
            ->where('assignable_type', $model)
            ->pluck('assignable_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        foreach (array_diff($ids, $existing) as $id) {
            $this->record->assignments()->create([
                'assignable_type' => $model,
                'assignable_id' => $id,
            ]);
        }

        $toRemove = array_diff($existing, $ids);
        if ($toRemove) {
            $this->record->assignments()
                ->where('assignable_type', $model)
                ->whereIn('assignable_id', $toRemove)
                ->delete();
        }
    }
}
