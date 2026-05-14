<?php

namespace SubKit\Concerns;

use Illuminate\Support\Facades\Cache;
use SubKit\Models\Plan;

/**
 * Backend-only trait for host-app billable models (User, Team, etc.).
 *
 * Exposes technical limits for the model's active subscription plan.
 * Features (marketing/UI) are intentionally excluded — use the PricingTable
 * component for feature display; use this trait for engine logic.
 *
 * Usage:
 *   use SubKit\Concerns\HasCapabilities;
 *
 *   class User extends Authenticatable {
 *       use Billable, HasCapabilities;
 *   }
 *
 * Cache is flushed automatically when a subscription webhook is received,
 * provided 'subkit.billable_model' is set in config/subkit.php.
 */
trait HasCapabilities
{
    public function getCapabilities(): array
    {
        return Cache::remember(
            "subkit.capabilities.{$this->getKey()}",
            300,
            fn () => $this->resolveCapabilities()
        );
    }

    public function flushCapabilitiesCache(): void
    {
        Cache::forget("subkit.capabilities.{$this->getKey()}");
    }

    private function resolveCapabilities(): array
    {
        $empty = ['limits' => []];

        $subscription = $this->subscriptions()
            ->where('stripe_status', 'active')
            ->first();

        if (! $subscription) {
            return $empty;
        }

        $stripePrice = $subscription->items->first()?->stripe_price;

        if (! $stripePrice) {
            return $empty;
        }

        $plan = Plan::whereHas(
            'providerPrices',
            fn ($q) => $q->where('provider_price_id', $stripePrice)
        )->with('limits')->first();

        if (! $plan) {
            return $empty;
        }

        return [
            'limits' => $plan->limits
                ->mapWithKeys(fn ($limit) => [$limit->key => $limit->casted_value])
                ->toArray(),
        ];
    }
}
