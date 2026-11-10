<?php

namespace SubKit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A one-off purchasable product (consumable pack, credit top-up). Not a Plan and not a
 * Subscription: SubKit only launches the checkout, the host app owns what is granted (ADR-003).
 *
 * The Top-up itself is provider-neutral. Which providers can sell it, and under which
 * provider reference, is stored in providerPrices() (like Plans).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $amount Display amount in minor units. The provider stays authoritative for the charge.
 * @property string|null $currency ISO code; null means the configured default
 * @property bool $is_active
 * @property-read string|null $formatted_price
 */
class TopUp extends Model
{
    protected $table = 'subkit_top_ups';

    protected $fillable = [
        'code',
        'name',
        'description',
        'amount',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (TopUp $topUp): void {
            $topUp->providerPrices()->delete();
        });
    }

    /**
     * @return HasMany<TopUpProviderPrice, $this>
     */
    public function providerPrices(): HasMany
    {
        return $this->hasMany(TopUpProviderPrice::class);
    }

    public function providerPrice(string $provider): ?TopUpProviderPrice
    {
        /** @var TopUpProviderPrice|null $price */
        $price = $this->providerPrices()
            ->where('provider', $provider)
            ->where('provider_price_id', '!=', '')
            ->first();

        return $price;
    }

    /**
     * Active top-ups that the given provider can sell.
     *
     * @param  Builder<TopUp>  $query
     */
    public function scopeAvailable(Builder $query, string $provider): void
    {
        $query->where('is_active', true)->whereHas(
            'providerPrices',
            fn (Builder $prices) => $prices->where('provider', $provider)->where('provider_price_id', '!=', '')
        );
    }

    /**
     * Display price, e.g. "$99.00". Null when no amount is set.
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->amount === null) {
                    return null;
                }

                $default = strtoupper((string) config('subkit.currency.code', 'USD'));
                $currency = $this->currency ? strtoupper($this->currency) : $default;
                $prefix = $currency === $default ? config('subkit.currency.symbol', '$') : $currency.' ';

                return $prefix.number_format($this->amount / 100, 2);
            },
        );
    }
}
