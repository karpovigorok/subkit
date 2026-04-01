<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanSet extends Model
{

    protected $table = 'subkit_plan_sets';
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'theme',
        'success_url',
        'cancel_url',
        'free_url',
        'guest_url',
        'subscribe_label',
        'free_label',
        'guest_label',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlanSetItem::class)->orderBy('sort_order');
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'subkit_plan_set_items')
            ->withPivot('sort_order', 'is_highlighted')
            ->orderBy('subkit_plan_set_items.sort_order');
    }
}
