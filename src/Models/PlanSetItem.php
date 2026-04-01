<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanSetItem extends Model
{

    protected $table = 'subkit_plan_set_items';
    protected $fillable = [
        'plan_set_id',
        'plan_id',
        'sort_order',
        'is_highlighted',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'     => 'integer',
            'is_highlighted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Default sort_order to the next available position within the set
        static::creating(function (PlanSetItem $item): void {
            if ($item->sort_order === 0) {
                $item->sort_order = static::where('plan_set_id', $item->plan_set_id)->max('sort_order') + 1;
            }
        });
    }

    public function planSet(): BelongsTo
    {
        return $this->belongsTo(PlanSet::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
