<?php

namespace SubKit\Filament\Resources\PlanSetResource\Pages;

use SubKit\Filament\Resources\PlanSetResource;
use SubKit\Models\PlanSet;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePlanSet extends CreateRecord
{
    protected static string $resource = PlanSetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $base = Str::slug($data['name'] ?? '', '_');
        $code = $base ?: (string) Str::uuid();
        $i    = 2;

        while (PlanSet::where('code', $code)->exists()) {
            $code = $base . '_' . $i++;
        }

        $data['code'] = $code;

        return $data;
    }
}
