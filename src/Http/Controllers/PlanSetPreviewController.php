<?php

namespace SubKit\Http\Controllers;

use Illuminate\Http\Response;
use SubKit\Models\PlanSet;

class PlanSetPreviewController
{
    public function __invoke(string $code): Response
    {
        $planSet = PlanSet::where('code', $code)->firstOrFail();

        return response()->view('subkit::filament.plan-set-preview-page', [
            'planSet' => $planSet,
        ]);
    }
}
