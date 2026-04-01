<?php

namespace SubKit\Services;

use SubKit\Models\Plan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription as CashierSubscription;
use RuntimeException;

class SubscriptionService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    // -------------------------------------------------------------------------
    // Checkout
    // -------------------------------------------------------------------------

    /**
     * Create a hosted checkout session and return the redirect URL.
     *
     * @throws RuntimeException if the plan or its provider price is not found.
     */
    public function checkout(
        string $planCode,
        ?string $userId,
        string $successUrl,
        string $cancelUrl,
        string $provider = 'stripe',
        array $options = [],
    ): string {
        $plan = Plan::where('code', $planCode)->where('is_active', true)->firstOrFail();

        $providerPrice = $plan->providerPrice($provider)
            ?? throw new RuntimeException("Plan [{$planCode}] has no price for provider [{$provider}].");

        $user = $this->user($userId ?? throw new RuntimeException('A user ID is required for checkout.'));

        return $this->registry->resolve($provider)->createCheckoutSession(
            user:       $user,
            priceId:    $providerPrice->provider_price_id,
            successUrl: $successUrl,
            cancelUrl:  $cancelUrl,
            trialDays:  $plan->trial_days,
            options:    $options,
        );
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function forUser(string $userId): Collection
    {
        return $this->user($userId)->subscriptions;
    }

    public function activeForUser(string $userId): ?CashierSubscription
    {
        $sub = $this->user($userId)->subscription('default');

        return ($sub && ($sub->active() || $sub->onTrial())) ? $sub : null;
    }

    public function hasAccess(string $userId): bool
    {
        return $this->user($userId)->subscribed('default');
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    public function cancel(int $subscriptionId, bool $immediately = false): void
    {
        $sub = CashierSubscription::findOrFail($subscriptionId);

        $this->registry
            ->resolve('stripe')
            ->cancelSubscription($sub->user, $immediately);
    }

    public function resume(int $subscriptionId): void
    {
        $sub = CashierSubscription::findOrFail($subscriptionId);

        $this->registry
            ->resolve('stripe')
            ->resumeSubscription($sub->user);
    }

    // -------------------------------------------------------------------------
    // Billing portal
    // -------------------------------------------------------------------------

    public function billingPortal(int $subscriptionId, string $returnUrl): string
    {
        $sub = CashierSubscription::findOrFail($subscriptionId);

        return $this->registry
            ->resolve('stripe')
            ->createBillingPortalSession($sub->user, $returnUrl);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function user(string $userId): Model
    {
        $class = config('auth.providers.users.model', \App\Models\User::class);

        return $class::findOrFail($userId);
    }
}
