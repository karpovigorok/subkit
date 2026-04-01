<?php

namespace SubKit\Contracts;

use Illuminate\Database\Eloquent\Model;

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
     */
    public function createCheckoutSession(
        Model $user,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        ?int $trialDays = null,
        array $options = [],
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
