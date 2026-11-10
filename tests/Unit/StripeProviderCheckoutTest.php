<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Mockery;
use SubKit\Models\TopUp;
use SubKit\Providers\Stripe\StripeProvider;
use Tests\TestCase;

class StripeProviderCheckoutTest extends TestCase
{
    private function checkoutResult(string $url): object
    {
        return (object) ['url' => $url];
    }

    public function test_subscription_metadata_goes_through_the_builder_and_transfer_data_through_session_options(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('withMetadata')->once()->with(['customer_id' => '7']);
        $builder->shouldReceive('checkout')->once()->with([
            'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no',
            'subscription_data' => [
                'transfer_data' => ['destination' => 'acct_1ABC'],
                'application_fee_percent' => 20,
            ],
        ])->andReturn($this->checkoutResult('https://stripe.test/s'));

        $user = Mockery::mock(Model::class);
        $user->shouldReceive('newSubscription')->with('default', 'price_1')->andReturn($builder);

        $url = (new StripeProvider)->createCheckoutSession(
            user: $user, priceId: 'price_1', successUrl: 'https://x.test/ok', cancelUrl: 'https://x.test/no',
            extras: [
                'metadata' => ['customer_id' => '7'],
                'transfer_data' => ['destination' => 'acct_1ABC'],
                'application_fee_percent' => 20,
            ],
        );

        $this->assertSame('https://stripe.test/s', $url);
    }

    public function test_subscription_checkout_without_extras_is_unchanged(): void
    {
        $builder = Mockery::mock();
        $builder->shouldNotReceive('withMetadata');
        $builder->shouldReceive('checkout')->once()->with([
            'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no',
        ])->andReturn($this->checkoutResult('https://stripe.test/s'));

        $user = Mockery::mock(Model::class);
        $user->shouldReceive('newSubscription')->andReturn($builder);

        (new StripeProvider)->createCheckoutSession($user, 'price_1', 'https://x.test/ok', 'https://x.test/no');
    }

    public function test_top_up_metadata_is_set_on_session_and_payment_intent(): void
    {
        $user = Mockery::mock(Model::class);
        $user->shouldReceive('checkout')->once()->with(['price_topup' => 1], [
            'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no',
            'metadata' => ['subkit_kind' => 'top-up'],
            'payment_intent_data' => [
                'metadata' => ['subkit_kind' => 'top-up'],
                'transfer_data' => ['destination' => 'acct_1ABC'],
                'application_fee_amount' => 250,
            ],
        ])->andReturn($this->checkoutResult('https://stripe.test/t'));

        $url = (new StripeProvider)->createTopUpCheckoutSession(
            $user, new TopUp, 'price_topup', 'https://x.test/ok', 'https://x.test/no',
            ['metadata' => ['subkit_kind' => 'top-up'], 'transfer_data' => ['destination' => 'acct_1ABC'], 'application_fee_amount' => 250],
        );

        $this->assertSame('https://stripe.test/t', $url);
    }
}
