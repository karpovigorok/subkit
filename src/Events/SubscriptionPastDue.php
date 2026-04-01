<?php

namespace SubKit\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravel\Cashier\Subscription as CashierSubscription;

class SubscriptionPastDue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $user,
        public readonly ?CashierSubscription $subscription,
        public readonly array $payload,
    ) {}
}
