<?php

namespace SubKit\Facades;

use SubKit\Services\SubscriptionService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string checkout(string $planCode, ?string $userId, string $successUrl, string $cancelUrl, string $provider = 'stripe', array $options = [])
 * @method static \Illuminate\Database\Eloquent\Collection forUser(string $userId)
 * @method static \Laravel\Cashier\Subscription|null activeForUser(string $userId)
 * @method static bool hasAccess(string $userId)
 * @method static void cancel(int $subscriptionId, bool $immediately = false)
 * @method static void resume(int $subscriptionId)
 * @method static string billingPortal(int $subscriptionId, string $returnUrl)
 */
class SubKit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SubscriptionService::class;
    }
}
