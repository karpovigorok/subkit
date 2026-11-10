<?php

namespace SubKit\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use SubKit\Models\TopUp;

/**
 * A Top-up has been paid. Provider-neutral: the same host listener works for every provider.
 *
 * SubKit stores nothing about the purchase. The event can be delivered more than once
 * (providers redeliver webhooks), so the host MUST de-duplicate on $reference before
 * granting anything.
 */
class TopUpPurchased
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Model  $billable  The billable that bought it (User, Team, ...).
     * @param  string  $topUpCode  Code of the Top-up. Always set, even if the Top-up was deleted since.
     * @param  TopUp|null  $topUp  The Top-up record, or null if it no longer exists.
     * @param  string  $provider  e.g. "stripe".
     * @param  string  $reference  Provider reference of the purchase (Stripe Checkout Session ID). Use it to de-duplicate.
     * @param  array<string, string>  $metadata  Metadata your checkout hook added (SubKit's own keys removed).
     * @param  int|null  $amount  Amount paid in minor units, as reported by the provider.
     * @param  string|null  $currency  Upper-case ISO currency code, as reported by the provider.
     * @param  array<string, mixed>  $payload  Raw provider payload.
     */
    public function __construct(
        public readonly Model $billable,
        public readonly string $topUpCode,
        public readonly ?TopUp $topUp,
        public readonly string $provider,
        public readonly string $reference,
        public readonly array $metadata,
        public readonly ?int $amount,
        public readonly ?string $currency,
        public readonly array $payload,
    ) {}
}
