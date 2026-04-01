<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SubKit\Enums\SubscriptionInterval;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property SubscriptionInterval $interval
 * @property int|null $trial_days
 * @property int|null $price
 * @property bool $is_active
 * @property int $version
 * @property array|null $metadata
 * @property-read string $formatted_price
 */

class Plan extends Model
{
    protected $table = 'subkit_plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'interval',
        'trial_days',
        'price',
        'is_active',
        'version',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'interval' => SubscriptionInterval::class,
            'trial_days' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
            'version' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Display price formatted with the configured currency symbol.
     * Returns "Free" when price is null or zero.
     * Example: 999 → "$9.99"
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (empty($this->price)) {
                    return 'Free';
                }

                $symbol = config('subkit.currency.symbol', '$');

                return $symbol.number_format($this->price / 100, 2);
            },
        );
    }

    public function providerPrices(): HasMany
    {
        return $this->hasMany(PlanProviderPrice::class);
    }

    public function providerPrice(string $provider): ?PlanProviderPrice
    {
        return $this->providerPrices()
            ->where('provider', $provider)
            ->whereNotNull('provider_price_id')
            ->where('provider_price_id', '!=', '')
            ->first();
    }

    public function planSets(): BelongsToMany
    {
        return $this->belongsToMany(PlanSet::class, 'subkit_plan_set_items')
            ->withPivot('sort_order', 'is_highlighted');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'subkit_feature_plan')
            ->withPivot('value', 'is_highlighted', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
