<?php

namespace SubKit\Filament\Resources\PlanResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use SubKit\Filament\Resources\PlanResource;
use SubKit\Models\Plan;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    private array $pendingAssigneeIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['name'] ?? '', '_');
        $code = $base ?: (string) Str::uuid();
        $i = 2;

        while (Plan::where('code', $code)->exists()) {
            $code = $base.'_'.$i++;
        }

        $data['code'] = $code;

        $this->pendingAssigneeIds = array_map('strval', $data['assignee_ids'] ?? []);
        unset($data['assignee_ids']);

        return $data;
    }

    protected function afterCreate(): void
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
