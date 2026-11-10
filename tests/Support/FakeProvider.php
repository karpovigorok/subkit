<?php

namespace Tests\Support;

use Illuminate\Database\Eloquent\Model;
use SubKit\Contracts\PaymentProviderContract;
use SubKit\Models\TopUp;

/** Records what SubKit hands to a provider, so tests never touch Stripe. */
class FakeProvider implements PaymentProviderContract
{
    /** @var array<string, mixed>|null */
    public ?array $subscriptionCall = null;

    /** @var array<string, mixed>|null */
    public ?array $topUpCall = null;

    public function name(): string
    {
        return 'fake';
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
        $this->subscriptionCall = compact('priceId', 'options', 'extras');

        return 'https://fake.test/subscription';
    }

    public function createTopUpCheckoutSession(
        Model $user,
        TopUp $topUp,
        string $priceId,
        string $successUrl,
        string $cancelUrl,
        array $extras = [],
    ): string {
        $this->topUpCall = compact('topUp', 'priceId', 'extras');

        return 'https://fake.test/top-up';
    }

    public function cancelSubscription(Model $user, bool $immediately = false): void {}

    public function resumeSubscription(Model $user): void {}

    public function createBillingPortalSession(Model $user, string $returnUrl): string
    {
        return 'https://fake.test/portal';
    }
}
