<?php

namespace SubKit\Filament\Resources\PlanResource\Pages;

use SubKit\Filament\Resources\PlanResource;
use SubKit\Models\Plan;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['name'] ?? '', '_');
        $code = $base ?: (string) Str::uuid();
        $i    = 2;

        while (Plan::where('code', $code)->exists()) {
            $code = $base . '_' . $i++;
        }

        $data['code'] = $code;

        return $data;
    }
}
