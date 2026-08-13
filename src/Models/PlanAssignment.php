<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlanAssignment extends Model
{
    protected $table = 'subkit_plan_assignments';

    protected $fillable = ['plan_id', 'assignable_type', 'assignable_id'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
