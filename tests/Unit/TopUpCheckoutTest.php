<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use SubKit\Checkout\CheckoutContext;
use SubKit\Exceptions\InvalidCheckoutOptionsException;
use SubKit\Models\TopUp;
use SubKit\Models\TopUpProviderPrice;
use SubKit\Services\ProviderRegistry;
use SubKit\Services\SubscriptionService;
use Tests\Support\FakeProvider;
use Tests\TestCase;

class TopUpCheckoutTest extends TestCase
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

    private function topUp(array $overrides = [], string $provider = 'fake'): TopUp
    {
        $topUp = TopUp::create($overrides + [
            'code' => 'credits-100', 'name' => '100 credits', 'is_active' => true,
        ]);
        TopUpProviderPrice::create(['top_up_id' => $topUp->id, 'provider' => $provider, 'provider_price_id' => 'price_topup']);

        return $topUp;
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function startTopUp(?User $user = null)
    {
        return $this->service()->topUpCheckout('credits-100', $user ?? User::factory()->create(), 'https://x.test/ok', 'https://x.test/no', 'fake');
    }

    public function test_top_up_checkout_uses_the_configured_price_and_reserved_metadata(): void
    {
        $this->topUp();

        $result = $this->startTopUp();

        $this->assertSame('https://fake.test/top-up', $result->url);
        $this->assertFalse($result->directlySubscribed);
        $this->assertSame('price_topup', $this->provider->topUpCall['priceId']);
        $this->assertSame('credits-100', $this->provider->topUpCall['topUp']->code);
        $this->assertSame(
            ['subkit_kind' => 'top-up', 'topup_code' => 'credits-100'],
            $this->provider->topUpCall['extras']['metadata'],
        );
    }

    public function test_hook_metadata_is_merged_with_reserved_keys(): void
    {
        $this->topUp();
        $seen = null;
        $this->service()->resolveCheckoutUsing(function (CheckoutContext $c) use (&$seen) {
            $seen = $c;

            return [
                'metadata' => ['customer_id' => 'c_1'],
                'transfer_data' => ['destination' => 'acct_1ABC'],
                'application_fee_amount' => 250,
            ];
        });

        $this->startTopUp();

        $this->assertSame(CheckoutContext::KIND_TOP_UP, $seen->kind);
        $this->assertSame('credits-100', $seen->topUp->code);
        $this->assertNull($seen->plan);
        $extras = $this->provider->topUpCall['extras'];
        $this->assertSame('c_1', $extras['metadata']['customer_id']);
        $this->assertSame('top-up', $extras['metadata']['subkit_kind']);
        $this->assertSame(250, $extras['application_fee_amount']);
        $this->assertSame(['destination' => 'acct_1ABC'], $extras['transfer_data']);
    }

    public function test_hook_cannot_spoof_reserved_keys(): void
    {
        $this->topUp();
        $this->service()->resolveCheckoutUsing(fn () => ['metadata' => ['topup_code' => 'other']]);

        $this->expectException(InvalidCheckoutOptionsException::class);

        $this->startTopUp();
    }

    public function test_percent_fee_is_rejected_for_top_ups(): void
    {
        $this->topUp();
        $this->service()->resolveCheckoutUsing(fn () => [
            'transfer_data' => ['destination' => 'acct_1ABC'],
            'application_fee_percent' => 10,
        ]);

        $this->expectException(InvalidCheckoutOptionsException::class);

        $this->startTopUp();
    }

    public function test_inactive_unknown_or_other_provider_top_ups_are_not_found(): void
    {
        $this->topUp(['is_active' => false]);

        $this->expectException(ModelNotFoundException::class);
        $this->startTopUp();
    }

    public function test_top_up_for_another_provider_is_not_found(): void
    {
        $this->topUp(provider: 'stripe');

        $this->expectException(ModelNotFoundException::class);
        $this->startTopUp();
    }

    public function test_web_route_requires_authentication(): void
    {
        $this->topUp();

        $this->post('/subkit/top-ups/checkout', [
            'top_up_code' => 'credits-100', 'success_url' => 'https://x.test/ok', 'cancel_url' => 'https://x.test/no', 'provider' => 'fake',
        ])->assertStatus(401);

        $this->assertNull($this->provider->topUpCall);
    }

    public function test_web_route_redirects_and_ignores_client_supplied_fields(): void
    {
        $this->topUp();

        $this->actingAs(User::factory()->create())->post('/subkit/top-ups/checkout', [
            'top_up_code' => 'credits-100',
            'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no',
            'provider' => 'fake',
            'price_id' => 'price_attacker',
            'quantity' => 1000,
            'metadata' => ['customer_id' => 'attacker'],
            'transfer_data' => ['destination' => 'acct_ATTACKER'],
        ])->assertRedirect('https://fake.test/top-up');

        $this->assertSame('price_topup', $this->provider->topUpCall['priceId']);
        $this->assertSame(
            ['subkit_kind' => 'top-up', 'topup_code' => 'credits-100'],
            $this->provider->topUpCall['extras']['metadata'],
        );
        $this->assertArrayNotHasKey('transfer_data', $this->provider->topUpCall['extras']);
    }

    public function test_api_route_returns_json(): void
    {
        $this->topUp();

        $this->actingAs(User::factory()->create())->postJson('/api/subkit/top-ups/checkout', [
            'top_up_code' => 'credits-100', 'success_url' => 'https://x.test/ok', 'cancel_url' => 'https://x.test/no', 'provider' => 'fake',
        ])->assertOk()->assertJson(['checkout_url' => 'https://fake.test/top-up']);
    }
}
