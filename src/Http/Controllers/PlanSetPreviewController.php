<?php

namespace SubKit\Http\Controllers;

use SubKit\Models\PlanSet;
use Illuminate\Http\Response;

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