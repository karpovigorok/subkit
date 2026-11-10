<?php

namespace Tests\Unit;

use App\Models\User;
use SubKit\Checkout\CheckoutContext;
use SubKit\Exceptions\InvalidCheckoutOptionsException;
use SubKit\Models\Plan;
use SubKit\Models\PlanProviderPrice;
use SubKit\Services\ProviderRegistry;
use SubKit\Services\SubscriptionService;
use Tests\Support\FakeProvider;
use Tests\TestCase;

class CheckoutHookTest extends TestCase
{
    private FakeProvider $provider;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('subkit.billable_model', User::class);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new FakeProvider;
        app(ProviderRegistry::class)->register($this->provider);
    }

    protected function tearDown(): void
    {
        app(SubscriptionService::class)->resolveCheckoutUsing(null);

        parent::tearDown();
    }

    private function plan(?int $price = 1000): Plan
    {
        $plan = Plan::create([
            'code' => 'pro', 'name' => 'Pro', 'interval' => 'monthly',
            'price' => $price, 'is_active' => true, 'version' => 1,
        ]);
        PlanProviderPrice::create(['plan_id' => $plan->id, 'provider' => 'fake', 'provider_price_id' => 'price_fake']);

        return $plan;
    }

    private function checkout(?User $user = null)
    {
        $user ??= User::factory()->create();

        return app(SubscriptionService::class)->checkout('pro', (string) $user->id, 'https://x.test/ok', 'https://x.test/no', 'fake');
    }

    public function test_without_a_hook_nothing_extra_is_sent(): void
    {
        $this->plan();

        $result = $this->checkout();

        $this->assertSame('https://fake.test/subscription', $result->url);
        $this->assertSame([], $this->provider->subscriptionCall['extras']);
    }

    public function test_hook_output_is_validated_and_passed_to_the_provider(): void
    {
        $this->plan();
        app(SubscriptionService::class)->resolveCheckoutUsing(fn () => [
            'metadata' => ['customer_id' => 7],
            'transfer_data' => ['destination' => 'acct_1ABC'],
            'application_fee_percent' => 20,
        ]);

        $this->checkout();

        $this->assertSame([
            'metadata' => ['customer_id' => '7'],
            'transfer_data' => ['destination' => 'acct_1ABC'],
            'application_fee_percent' => 20,
        ], $this->provider->subscriptionCall['extras']);
    }

    public function test_hook_receives_the_checkout_context(): void
    {
        $plan = $this->plan();
        $user = User::factory()->create();
        $seen = null;

        app(SubscriptionService::class)->resolveCheckoutUsing(function (CheckoutContext $c) use (&$seen) {
            $seen = $c;
        });

        $this->checkout($user);

        $this->assertSame(CheckoutContext::KIND_SUBSCRIPTION, $seen->kind);
        $this->assertTrue($seen->user->is($user));
        $this->assertTrue($seen->plan->is($plan));
        $this->assertSame('fake', $seen->provider);
        $this->assertNull($seen->topUp);
    }

    public function test_invalid_hook_output_aborts_before_the_provider_is_called(): void
    {
        $this->plan();
        app(SubscriptionService::class)->resolveCheckoutUsing(fn () => ['metadata' => ['type' => 'x']]);

        try {
            $this->checkout();
            $this->fail('Expected InvalidCheckoutOptionsException');
        } catch (InvalidCheckoutOptionsException) {
            $this->assertNull($this->provider->subscriptionCall);
        }
    }

    public function test_hook_is_not_called_for_free_plans(): void
    {
        $plan = $this->plan(price: null);
        $plan->providerPrices()->delete();
        $called = false;
        app(SubscriptionService::class)->resolveCheckoutUsing(function () use (&$called) {
            $called = true;
        });

        $result = $this->checkout();

        $this->assertTrue($result->directlySubscribed);
        $this->assertFalse($called);
    }

    public function test_client_supplied_fields_never_reach_the_provider(): void
    {
        $this->plan();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/subkit/checkout', [
            'plan_code' => 'pro',
            'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no',
            'provider' => 'fake',
            'metadata' => ['customer_id' => 'attacker'],
            'transfer_data' => ['destination' => 'acct_ATTACKER'],
            'application_fee_percent' => 99,
        ])->assertRedirect('https://fake.test/subscription');

        $this->assertSame([], $this->provider->subscriptionCall['extras']);
        $this->assertSame([], $this->provider->subscriptionCall['options']);
    }

    public function test_client_supplied_fields_never_reach_the_provider_via_the_api(): void
    {
        $this->plan();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/subkit/checkout', [
            'plan_code' => 'pro',
            'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no',
            'provider' => 'fake',
            'metadata' => ['customer_id' => 'attacker'],
            'transfer_data' => ['destination' => 'acct_ATTACKER'],
        ])->assertOk()->assertJson(['checkout_url' => 'https://fake.test/subscription']);

        $this->assertSame([], $this->provider->subscriptionCall['extras']);
    }
}
