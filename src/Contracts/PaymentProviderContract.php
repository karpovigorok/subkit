<?php

namespace SubKit\Contracts;

use Illuminate\Database\Eloquent\Model;
use SubKit\Models\TopUp;

interface PaymentProviderContract
{
    /**
     * Unique identifier for this provider, e.g. "stripe".
     */
    public function name(): string;

    /**
     * Create a hosted checkout session and return the redirect URL.
     *
     * @param  array<string, mixed>  $options  Provider-specific overrides passed to the checkout builder.
     * @param  array<string, mixed>  $extras  Validated output of the checkout hook (metadata, transfer_data,
     *                                        application_fee_percent). See SubKit\Checkout\CheckoutOptions.
     */
    public function createCheckoutSession(
        Model $user,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        ?int $trialDays = null,
        array $options = [],
        array $extras = [],
    ): string;

    /**
     * Create a hosted one-off (non-subscription) checkout session for a Top-up and return the redirect URL.
     *
     * The adapter receives the provider-neutral Top-up (name, display amount, currency) and this provider's
     * own reference for it ($priceId), so providers without a price catalog can build the payment themselves.
     *
     * @param  array<string, mixed>  $extras  Validated hook output, with SubKit's reserved metadata already merged
     *                                        (metadata, transfer_data, application_fee_amount).
     */
    public function createTopUpCheckoutSession(
        Model $user,
        TopUp $topUp,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $extras = [],
    ): string;

    /**
     * Cancel the user's subscription.
     * $immediately = false  →  cancel at period end (grace period)
     * $immediately = true   →  cancel right now
     */
    public function cancelSubscription(Model $user, bool $immediately = false): void;

    /**
     * Resume a subscription that is on a grace period (scheduled to cancel at period end).
     */
    public function resumeSubscription(Model $user): void;

    /**
     * Create a billing portal session and return its URL.
     */
    public function createBillingPortalSession(Model $user, string $returnUrl): string;
}
