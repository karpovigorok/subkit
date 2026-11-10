<?php

namespace SubKit\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Laravel\Cashier\Subscription;
use SubKit\Models\CheckoutResult;
use SubKit\Services\SubscriptionService;

/**
 * @method static string checkout(string $planCode, string|Model|null $userId, string $successUrl, string $cancelUrl, string $provider = 'stripe', array $options = [])
 * @method static CheckoutResult topUpCheckout(string $topUpCode, Model $user, string $successUrl, string $cancelUrl, string $provider = 'stripe')
 * @method static void authorizeBillableUsing(callable|null $callback) callback(Authenticatable $actor, Model $billable, string $ability): bool
 * @method static void resolveBillableUsing(callable|null $callback) callback(Request $request): ?Model
 * @method static void resolveCheckoutUsing(callable|null $callback) callback(CheckoutContext $context): mixed
 * @method static Collection forUser(string|Model $userId)
 * @method static Subscription|null activeForUser(string $userId)
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
