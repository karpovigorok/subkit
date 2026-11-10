<?php

namespace SubKit\Facades;

use Illuminate\Support\Facades\Facade;
use SubKit\Services\SubscriptionService;

/**
 * @method static string checkout(string $planCode, string|\Illuminate\Database\Eloquent\Model|null $userId, string $successUrl, string $cancelUrl, string $provider = 'stripe', array $options = [])
 * @method static \SubKit\Models\CheckoutResult topUpCheckout(string $topUpCode, \Illuminate\Database\Eloquent\Model $user, string $successUrl, string $cancelUrl, string $provider = 'stripe')
 * @method static void authorizeBillableUsing((callable(\Illuminate\Contracts\Auth\Authenticatable, \Illuminate\Database\Eloquent\Model, string): bool)|null $callback)
 * @method static void resolveBillableUsing((callable(\Illuminate\Http\Request): ?\Illuminate\Database\Eloquent\Model)|null $callback)
 * @method static void resolveCheckoutUsing((callable(\SubKit\Checkout\CheckoutContext): mixed)|null $callback)
 * @method static \Illuminate\Database\Eloquent\Collection forUser(string|\Illuminate\Database\Eloquent\Model $userId)
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
