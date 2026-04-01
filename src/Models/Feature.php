<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $table = 'subkit_features';

    protected $fillable = [
        'name',
        'description',
        'icon',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'subkit_feature_plan')
            ->withPivot('value', 'is_highlighted', 'sort_order')
            ->withTimestamps();
    }
}
