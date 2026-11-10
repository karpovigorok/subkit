<?php

namespace SubKit\Checkout;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use SubKit\Models\Plan;
use SubKit\Models\TopUp;

/**
 * Read-only description of the checkout being created, handed to the checkout hook
 * (see SubscriptionService::resolveCheckoutUsing()).
 */
final class CheckoutContext
{
    public const KIND_SUBSCRIPTION = 'subscription';

    public const KIND_TOP_UP = 'top-up';

    public function __construct(
        /** @var self::KIND_* */
        public readonly string $kind,
        public readonly Model $user,
        public readonly string $provider,
        public readonly ?Plan $plan = null,
        public readonly ?TopUp $topUp = null,
        public readonly ?Request $request = null,
    ) {}
}
