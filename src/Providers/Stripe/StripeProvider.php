<?php

namespace SubKit\Providers\Stripe;

use SubKit\Contracts\PaymentProviderContract;
use Illuminate\Database\Eloquent\Model;

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
    ): string {
        $builder = $user->newSubscription('default', $priceId);

        if ($trialDays > 0) {
            $builder->trialDays($trialDays);
        }

        return $builder->checkout(array_merge([
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
        ], $options))->url;
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
