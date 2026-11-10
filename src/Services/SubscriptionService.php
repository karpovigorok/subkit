<?php

namespace SubKit\Services;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription as CashierSubscription;
use RuntimeException;
use SubKit\Checkout\CheckoutContext;
use SubKit\Checkout\CheckoutOptions;
use SubKit\Models\CheckoutResult;
use SubKit\Models\Plan;
use SubKit\Models\TopUp;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubscriptionService
{
    /** Abilities passed to the authorizeBillableUsing() rule. */
    public const ABILITY_CHECKOUT = 'checkout';

    public const ABILITY_TOP_UP = 'top_up';

    public const ABILITY_VIEW = 'view';

    public const ABILITY_CANCEL = 'cancel';

    public const ABILITY_RESUME = 'resume';

    public const ABILITY_PORTAL = 'portal';

    /** @var (Closure(CheckoutContext): mixed)|null */
    private ?Closure $checkoutHook = null;

    /** @var (Closure(Authenticatable, Model, string): bool)|null */
    private ?Closure $billableGuard = null;

    /** @var (Closure(Request): ?Model)|null */
    private ?Closure $billableResolver = null;

    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    // -------------------------------------------------------------------------
    // Identity and authorization (ADR-001: identity comes from the server)
    // -------------------------------------------------------------------------

    /**
     * Register the rule deciding whether the authenticated user may act for a billable
     * other than themselves (a Team, Company, ...). Call it from a service provider's boot().
     *
     * Without a rule a user can only act for their own billable. The callback receives the
     * authenticated user, the target billable and the ability being exercised
     * (checkout, top_up, view, cancel, resume, portal) and returns true to allow. A callback
     * may declare only the first two parameters. Pass null to remove it.
     *
     * @param  (callable(Authenticatable, Model, string): bool)|null  $callback
     */
    public function authorizeBillableUsing(?callable $callback): void
    {
        $this->billableGuard = $callback === null ? null : Closure::fromCallable($callback);
    }

    /**
     * Register how a request names the billable it acts for when the client did not name one
     * (no `company_id`): the current Team or tenant, or the account behind a custom authentication.
     * Call it from a service provider's boot().
     *
     * The callback receives the request and returns the billable, or null when there is none
     * (the request is then rejected). Without a resolver the authenticated user is the billable.
     *
     * The result is trusted: it is server-side, like the checkout hook. When an
     * authorizeBillableUsing() rule is also registered it is still asked, per ability, whether
     * the authenticated user may act for the returned billable (use it for roles). Pass null to remove it.
     *
     * @param  (callable(Request): ?Model)|null  $callback
     */
    public function resolveBillableUsing(?callable $callback): void
    {
        $this->billableResolver = $callback === null ? null : Closure::fromCallable($callback);
    }

    /**
     * Resolve the billable an HTTP request acts for: the resolver's billable or the authenticated
     * user by default, or the billable named by $billableId when the host's rule allows it.
     *
     * @throws AuthorizationException when the target does not exist or the user may not act for it.
     * @throws HttpException 401 when there is no identity.
     */
    public function resolveBillable(Request $request, ?string $billableId = null, string $ability = self::ABILITY_CHECKOUT): Model
    {
        $actor = $request->user();
        $default = $this->requestBillable($request);

        if ($billableId === null || $billableId === '') {
            if ($this->billableResolver === null) {
                $actor !== null || abort(401);

                return $actor instanceof Model ? $actor : throw new AuthorizationException;
            }

            if ($default === null) {
                $actor !== null || abort(401);

                throw new AuthorizationException;
            }

            return $this->mayActFor($actor, $default, $ability, trusted: true)
                ? $default
                : throw new AuthorizationException;
        }

        $actor !== null || abort(401);

        $billable = $this->billableClass()::find($billableId);

        // Same response for "missing" and "not yours" so ids cannot be enumerated.
        if (! $billable instanceof Model
            || ! $this->mayActFor($actor, $billable, $ability, trusted: $default !== null && $billable->is($default))) {
            throw new AuthorizationException;
        }

        return $billable;
    }

    /**
     * Load a subscription for an HTTP action, only if the request may act for its owner.
     *
     * @throws AuthorizationException
     * @throws HttpException 401 when there is no identity.
     */
    public function findAuthorizedSubscription(Request $request, int $subscriptionId, string $ability): CashierSubscription
    {
        $actor = $request->user();
        $default = $this->requestBillable($request);

        ($actor !== null || $default !== null) || abort(401);

        $sub = CashierSubscription::find($subscriptionId);
        $owner = $sub?->owner;

        if ($sub === null || ! $owner instanceof Model
            || ! $this->mayActFor($actor, $owner, $ability, trusted: $default !== null && $owner->is($default))) {
            throw new AuthorizationException;
        }

        return $sub;
    }

    /**
     * Own billable: always. Otherwise the host's rule decides, and when there is none only a
     * $trusted billable (one the host's resolver returned) is allowed.
     */
    private function mayActFor(?Authenticatable $actor, Model $billable, string $ability, bool $trusted = false): bool
    {
        if ($actor instanceof Model && $actor->is($billable)) {
            return true;
        }

        if ($actor !== null && $this->billableGuard !== null) {
            return (bool) ($this->billableGuard)($actor, $billable, $ability);
        }

        return $trusted;
    }

    private function requestBillable(Request $request): ?Model
    {
        if ($this->billableResolver === null) {
            return null;
        }

        $billable = ($this->billableResolver)($request);

        if ($billable !== null && ! $billable instanceof Model) {
            throw new \LogicException('The resolveBillableUsing() callback must return an Eloquent model or null.');
        }

        return $billable;
    }

    // -------------------------------------------------------------------------
    // Checkout hook (ADR-002)
    // -------------------------------------------------------------------------

    /**
     * Register the server-side checkout hook. Call it from a service provider's boot().
     *
     * The callback receives a CheckoutContext and returns null or an array with any of
     * `metadata`, `transfer_data` and `application_fee_percent` (subscriptions) or
     * `application_fee_amount` (top-ups). This is the ONLY source of these values:
     * they are never read from the request. Pass null to remove the hook.
     *
     * @param  (callable(CheckoutContext): mixed)|null  $callback
     */
    public function resolveCheckoutUsing(?callable $callback): void
    {
        $this->checkoutHook = $callback === null ? null : Closure::fromCallable($callback);
    }

    /**
     * @return array<string, mixed> Validated hook output, [] when no hook is registered.
     */
    private function resolveExtras(CheckoutContext $context): array
    {
        if ($this->checkoutHook === null) {
            return [];
        }

        return CheckoutOptions::validate(($this->checkoutHook)($context), $context->kind);
    }

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
        string|Model|null $userId,
        string $successUrl,
        string $cancelUrl,
        string $provider = 'stripe',
        array $options = [],
    ): CheckoutResult {
        $plan = Plan::where('code', $planCode)->where('is_active', true)->firstOrFail();

        $providerPrice = $plan->providerPrice($provider); // nullable — checked below

        $user = $this->user($userId ?? throw new RuntimeException('A user is required for checkout.'));

        if ($plan->price === null || $plan->price === 0) {
            $this->subscribeDirectly($user, $providerPrice?->provider_price_id, $plan->code);

            return new CheckoutResult(url: $successUrl, directlySubscribed: true);
        }

        if ($providerPrice === null) {
            throw new RuntimeException("Plan [{$planCode}] has no price for provider [{$provider}].");
        }

        $extras = $this->resolveExtras(new CheckoutContext(
            kind: CheckoutContext::KIND_SUBSCRIPTION,
            user: $user,
            provider: $provider,
            plan: $plan,
            request: $this->currentRequest(),
        ));

        $url = $this->registry->resolve($provider)->createCheckoutSession(
            user: $user,
            priceId: $providerPrice->provider_price_id,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            trialDays: $plan->trial_days,
            options: $options,
            extras: $extras,
        );

        return new CheckoutResult(url: $url, directlySubscribed: false);
    }

    /**
     * Create a one-off checkout for a Top-up (ADR-003). SubKit only launches the checkout;
     * the host listens to Cashier's WebhookHandled event and grants whatever was purchased.
     *
     * The buyer is passed as a model so callers resolve identity themselves
     * (e.g. $request->user(), or a Team for B2B). Quantity is fixed by the Top-up definition.
     *
     * @throws ModelNotFoundException if the Top-up is unknown, inactive or for another provider.
     */
    public function topUpCheckout(
        string $topUpCode,
        Model $user,
        string $successUrl,
        string $cancelUrl,
        string $provider = 'stripe',
    ): CheckoutResult {
        $topUp = TopUp::where('code', $topUpCode)->where('is_active', true)->first();
        $providerPrice = $topUp?->providerPrice($provider);

        if ($topUp === null || $providerPrice === null) {
            throw (new ModelNotFoundException)->setModel(TopUp::class, [$topUpCode]);
        }

        $extras = $this->resolveExtras(new CheckoutContext(
            kind: CheckoutContext::KIND_TOP_UP,
            user: $user,
            provider: $provider,
            topUp: $topUp,
            request: $this->currentRequest(),
        ));

        // Reserved keys are applied last and were rejected from the hook, so they cannot be spoofed.
        $extras['metadata'] = array_merge($extras['metadata'] ?? [], [
            'subkit_kind' => CheckoutContext::KIND_TOP_UP,
            'topup_code' => $topUp->code,
        ]);

        $url = $this->registry->resolve($provider)->createTopUpCheckoutSession(
            user: $user,
            topUp: $topUp,
            priceId: $providerPrice->provider_price_id,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            extras: $extras,
        );

        return new CheckoutResult(url: $url, directlySubscribed: false);
    }

    private function currentRequest(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
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
                'type' => 'default',
                'stripe_id' => 'local_'.Str::uuid(),
                'stripe_status' => 'active',
                'stripe_price' => $planCode ? 'local:'.$planCode : null,
            ]);
        }

        if (method_exists($user, 'flushCapabilitiesCache')) {
            $user->flushCapabilitiesCache();
        }
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    public function forUser(string|Model $userId): Collection
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

    private function user(string|Model $user): Model
    {
        return $user instanceof Model ? $user : $this->billableClass()::findOrFail($user);
    }

    /** @return class-string<Model> */
    private function billableClass(): string
    {
        return config('subkit.billable_model')
            ?? config('auth.providers.users.model');
    }
}
