<?php

namespace SubKit\Providers\Stripe;

use Illuminate\Database\Eloquent\Model;
use SubKit\Contracts\PaymentProviderContract;
use SubKit\Models\TopUp;

class StripeProvider implements PaymentProviderContract
{
    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(
        Model $user,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        ?int $trialDays = null,
        array $options = [],
        array $extras = [],
    ): string {
        $builder = $user->newSubscription('default', $priceId);

        if ($trialDays > 0) {
            $builder->trialDays($trialDays);
        }

        // Metadata goes through the builder so it lands on the Subscription (and survives renewals).
        // Cashier merges it safely with its own name/type keys; passing it as a session option would
        // collide under array_merge_recursive.
        if (! empty($extras['metadata'])) {
            $builder->withMetadata($extras['metadata']);
        }

        $sessionOptions = array_merge([
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], $options);

        $subscriptionData = array_filter([
            'transfer_data' => $extras['transfer_data'] ?? null,
            'application_fee_percent' => $extras['application_fee_percent'] ?? null,
        ]);

        if ($subscriptionData !== []) {
            $sessionOptions['subscription_data'] = array_merge($sessionOptions['subscription_data'] ?? [], $subscriptionData);
        }

        return $builder->checkout($sessionOptions)->url;
    }

    public function createTopUpCheckoutSession(
        Model $user,
        TopUp $topUp,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $extras = [],
    ): string {
        $metadata = $extras['metadata'] ?? [];

        // Metadata is set on the Checkout Session (delivered in checkout.session.completed) and on the
        // PaymentIntent (so refunds and disputes can be correlated with the purchase).
        $paymentIntentData = array_filter([
            'metadata' => $metadata ?: null,
            'transfer_data' => $extras['transfer_data'] ?? null,
            'application_fee_amount' => $extras['application_fee_amount'] ?? null,
        ]);

        $sessionOptions = array_filter([
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata ?: null,
            'payment_intent_data' => $paymentIntentData ?: null,
        ]);

        return $user->checkout([$priceId => 1], $sessionOptions)->url;
    }

    public function cancelSubscription(Model $user, bool $immediately = false): void
    {
        $immediately
            ? $user->subscription('default')->cancelNow()
            : $user->subscription('default')->cancel();
    }

    public function resumeSubscription(Model $user): void
    {
        $user->subscription('default')->resume();
    }

    public function createBillingPortalSession(Model $user, string $returnUrl): string
    {
        return $user->billingPortalUrl($returnUrl);
    }
}
