<?php

namespace SubKit\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription as CashierSubscription;
use RuntimeException;
use SubKit\Models\CheckoutResult;
use SubKit\Models\Plan;

class SubscriptionService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    // -------------------------------------------------------------------------
    // Checkout
    // -------------------------------------------------------------------------

    /**
     * Create a checkout session or subscribe directly for $0 plans.
     *
     * Returns a CheckoutResult with the redirect URL and whether the subscription
     * was created immediately (true for $0 plans) or via Stripe Checkout (false).
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
    ): CheckoutResult {
        $plan = Plan::where('code', $planCode)->where('is_active', true)->firstOrFail();

        $providerPrice = $plan->providerPrice($provider); // nullable — checked below

        $user = $this->user($userId ?? throw new RuntimeException('A user ID is required for checkout.'));

        if ($plan->price === null || $plan->price === 0) {
            $this->subscribeDirectly($user, $providerPrice?->provider_price_id, $plan->code);

            return new CheckoutResult(url: $successUrl, directlySubscribed: true);
        }

        if ($providerPrice === null) {
            throw new RuntimeException("Plan [{$planCode}] has no price for provider [{$provider}].");
        }

        $url = $this->registry->resolve($provider)->createCheckoutSession(
            user: $user,
            priceId: $providerPrice->provider_price_id,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            trialDays: $plan->trial_days,
            options: $options,
        );

        return new CheckoutResult(url: $url, directlySubscribed: false);
    }

    private function subscribeDirectly(Model $user, ?string $priceId, ?string $planCode = null): void
    {
        if ($priceId !== null) {
            $user->newSubscription('default', $priceId)->create();
        } else {
            // $0 plan with no Stripe price — create a local subscription record.
            // Use getForeignKey() so it works for any billable model (User → user_id, Team → team_id).
            // stripe_price stores 'local:{code}' so PersonalOffers can filter out already-claimed offers.
            // hasAccess() and getCapabilities() will work; cancel/resume/portal require a real Stripe price.
            CashierSubscription::create([
                $user->getForeignKey() => $user->getKey(),
                'type'          => 'default',
                'stripe_id'     => 'local_' . Str::uuid(),
                'stripe_status' => 'active',
                'stripe_price'  => $planCode ? 'local:' . $planCode : null,
            ]);
        }

        if (method_exists($user, 'flushCapabilitiesCache')) {
            $user->flushCapabilitiesCache();
        }
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

        if (str_starts_with($sub->stripe_id, 'local_')) {
            $sub->stripe_status = 'canceled';
            $sub->ends_at = now();
            $sub->save();
            return;
        }

        $this->registry
            ->resolve('stripe')
            ->cancelSubscription($sub->user, $immediately);
    }

    public function resume(int $subscriptionId): void
    {
        $sub = CashierSubscription::findOrFail($subscriptionId);

        if (str_starts_with($sub->stripe_id, 'local_')) {
            $sub->stripe_status = 'active';
            $sub->ends_at = null;
            $sub->save();
            return;
        }

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
        $class = config('subkit.billable_model',
            config('auth.providers.users.model', User::class));

        return $class::findOrFail($userId);
    }
}
