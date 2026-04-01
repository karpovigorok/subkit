<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanProviderPrice extends Model
{
    protected $table = 'subkit_plan_provider_prices';
    protected $fillable = [
        'plan_id',
        'provider',
        'provider_price_id',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
