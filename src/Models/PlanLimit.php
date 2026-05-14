<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanLimit extends Model
{
    protected $table = 'subkit_plan_limits';

    protected $fillable = ['plan_id', 'key', 'value', 'type'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    protected function castedValue(): Attribute
    {
        return Attribute::make(
            get: fn (): mixed => match ($this->type) {
                'int' => intval($this->value),
                'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
                default => $this->value,
            },
        );
    }
}
