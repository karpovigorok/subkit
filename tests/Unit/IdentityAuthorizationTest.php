<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription as CashierSubscription;
use SubKit\Checkout\CheckoutContext;
use SubKit\Models\Plan;
use SubKit\Models\PlanProviderPrice;
use SubKit\Services\ProviderRegistry;
use SubKit\Services\SubscriptionService;
use Tests\Support\FakeProvider;
use Tests\TestCase;

/**
 * Identity comes from the authenticated user, never from request input (ADR-001).
 */
class IdentityAuthorizationTest extends TestCase
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

        $plan = Plan::create([
            'code' => 'pro', 'name' => 'Pro', 'interval' => 'monthly',
            'price' => 1000, 'is_active' => true, 'version' => 1,
        ]);
        PlanProviderPrice::create(['plan_id' => $plan->id, 'provider' => 'fake', 'provider_price_id' => 'price_fake']);
    }

    protected function tearDown(): void
    {
        $service = app(SubscriptionService::class);
        $service->authorizeBillableUsing(null);
        $service->resolveCheckoutUsing(null);

        parent::tearDown();
    }

    private function subscriptionFor(User $user): CashierSubscription
    {
        return CashierSubscription::create([
            'user_id' => $user->id, 'type' => 'default', 'stripe_id' => 'local_'.uniqid(),
            'stripe_status' => 'active', 'stripe_price' => 'local:pro',
        ]);
    }

    private function checkoutPayload(array $extra = []): array
    {
        return $extra + [
            'plan_code' => 'pro', 'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no', 'provider' => 'fake',
        ];
    }

    // ── checkout ────────────────────────────────────────────────────────────

    public function test_checkout_acts_for_the_authenticated_user_by_default(): void
    {
        $me = User::factory()->create();
        $seen = null;
        app(SubscriptionService::class)->resolveCheckoutUsing(function (CheckoutContext $c) use (&$seen) {
            $seen = $c->user;
        });

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload())
            ->assertRedirect('https://fake.test/subscription');

        $this->assertTrue($seen->is($me));
    }

    public function test_company_id_naming_the_user_themselves_is_allowed(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload(['company_id' => (string) $me->id]))
            ->assertRedirect('https://fake.test/subscription');
    }

    public function test_company_id_of_another_billable_is_forbidden_without_a_rule(): void
    {
        $me = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload(['company_id' => (string) $victim->id]))
            ->assertForbidden();

        $this->assertNull($this->provider->subscriptionCall);
    }

    public function test_unknown_company_id_is_forbidden_not_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/subkit/checkout', $this->checkoutPayload(['company_id' => '99999']))
            ->assertForbidden();
    }

    public function test_host_rule_can_allow_acting_for_another_billable(): void
    {
        $me = User::factory()->create();
        $company = User::factory()->create(); // stands in for a Team/Company billable
        $args = null;
        $seen = null;

        $service = app(SubscriptionService::class);
        $service->authorizeBillableUsing(function (Authenticatable $actor, Model $billable) use ($me, $company, &$args) {
            $args = [$actor, $billable];

            return $actor->is($me) && $billable->is($company);
        });
        $service->resolveCheckoutUsing(function (CheckoutContext $c) use (&$seen) {
            $seen = $c->user;
        });

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload(['company_id' => (string) $company->id]))
            ->assertRedirect('https://fake.test/subscription');

        $this->assertTrue($args[0]->is($me));
        $this->assertTrue($seen->is($company));
    }

    public function test_host_rule_returning_false_is_forbidden(): void
    {
        app(SubscriptionService::class)->authorizeBillableUsing(fn () => false);
        $other = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post('/subkit/checkout', $this->checkoutPayload(['company_id' => (string) $other->id]))
            ->assertForbidden();
    }

    public function test_api_checkout_ignores_user_id_and_requires_authentication(): void
    {
        $me = User::factory()->create();
        $victim = User::factory()->create();
        $seen = null;
        app(SubscriptionService::class)->resolveCheckoutUsing(function (CheckoutContext $c) use (&$seen) {
            $seen = $c->user;
        });

        $this->postJson('/api/subkit/checkout', $this->checkoutPayload(['user_id' => (string) $victim->id]))
            ->assertUnauthorized();

        $this->actingAs($me)->postJson('/api/subkit/checkout', $this->checkoutPayload(['user_id' => (string) $victim->id]))
            ->assertOk();

        $this->assertTrue($seen->is($me));
    }

    public function test_unauthenticated_web_checkout_is_rejected(): void
    {
        $this->post('/subkit/checkout', $this->checkoutPayload())->assertUnauthorized();
    }

    // ── listing ─────────────────────────────────────────────────────────────

    public function test_api_subscription_list_is_scoped_to_the_authenticated_user(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->subscriptionFor($me);
        $this->subscriptionFor($other);

        $this->getJson('/api/subkit/subscriptions/user')->assertUnauthorized();

        $this->actingAs($me)->getJson('/api/subkit/subscriptions/user?user_id='.$other->id)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $mine->id);

        $this->actingAs($me)->getJson('/api/subkit/subscriptions/user?company_id='.$other->id)->assertForbidden();
    }

    // ── cancel / resume / portal ────────────────────────────────────────────

    public function test_cancel_and_resume_require_authentication(): void
    {
        $sub = $this->subscriptionFor(User::factory()->create());

        $this->post("/subkit/subscriptions/{$sub->id}/cancel")->assertUnauthorized();
        $this->post("/subkit/subscriptions/{$sub->id}/resume")->assertUnauthorized();
        $this->postJson("/api/subkit/subscriptions/{$sub->id}/cancel")->assertUnauthorized();
        $this->postJson("/api/subkit/subscriptions/{$sub->id}/resume")->assertUnauthorized();

        $this->assertSame('active', $sub->fresh()->stripe_status);
    }

    public function test_user_cannot_cancel_or_resume_another_users_subscription(): void
    {
        $me = User::factory()->create();
        $sub = $this->subscriptionFor(User::factory()->create());

        $this->actingAs($me)->post("/subkit/subscriptions/{$sub->id}/cancel")->assertForbidden();
        $this->actingAs($me)->postJson("/api/subkit/subscriptions/{$sub->id}/cancel")->assertForbidden();
        $this->actingAs($me)->postJson("/api/subkit/subscriptions/{$sub->id}/resume")->assertForbidden();
        $this->actingAs($me)->post('/subkit/subscriptions/999999/cancel')->assertForbidden();

        $this->assertSame('active', $sub->fresh()->stripe_status);
    }

    public function test_user_can_cancel_and_resume_their_own_subscription(): void
    {
        $me = User::factory()->create();
        $sub = $this->subscriptionFor($me);

        $this->actingAs($me)->post("/subkit/subscriptions/{$sub->id}/cancel")->assertRedirect();
        $this->assertSame('canceled', $sub->fresh()->stripe_status);

        $this->actingAs($me)->postJson("/api/subkit/subscriptions/{$sub->id}/resume")->assertOk();
        $this->assertSame('active', $sub->fresh()->stripe_status);
    }

    public function test_host_rule_extends_subscription_actions_to_the_owner_billable(): void
    {
        $me = User::factory()->create();
        $company = User::factory()->create();
        $sub = $this->subscriptionFor($company);

        $this->actingAs($me)->post("/subkit/subscriptions/{$sub->id}/cancel")->assertForbidden();

        app(SubscriptionService::class)->authorizeBillableUsing(fn ($actor, $billable) => $billable->is($company));

        $this->actingAs($me)->post("/subkit/subscriptions/{$sub->id}/cancel")->assertRedirect();
        $this->assertSame('canceled', $sub->fresh()->stripe_status);
    }

    public function test_billing_portal_is_not_available_for_another_users_subscription(): void
    {
        $sub = $this->subscriptionFor(User::factory()->create());
        $payload = ['subscription_id' => $sub->id, 'return_url' => 'https://x.test/back'];

        $this->post('/subkit/billing-portal', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->post('/subkit/billing-portal', $payload)->assertForbidden();
    }
}
