<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Cashier\Subscription as CashierSubscription;
use LogicException;
use SubKit\Checkout\CheckoutContext;
use SubKit\Models\Plan;
use SubKit\Models\PlanProviderPrice;
use SubKit\Models\TopUp;
use SubKit\Models\TopUpProviderPrice;
use SubKit\Services\ProviderRegistry;
use SubKit\Services\SubscriptionService;
use Tests\Support\FakeProvider;
use Tests\TestCase;

/**
 * How a host plugs its own billable resolution and per-ability rules into SubKit,
 * without SubKit knowing anything about teams, roles or the authentication in use.
 */
class BillableResolverTest extends TestCase
{
    private SubscriptionService $service;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('subkit.billable_model', User::class);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(ProviderRegistry::class)->register(new FakeProvider);
        $this->service = app(SubscriptionService::class);

        $plan = Plan::create([
            'code' => 'pro', 'name' => 'Pro', 'interval' => 'monthly',
            'price' => 1000, 'is_active' => true, 'version' => 1,
        ]);
        PlanProviderPrice::create(['plan_id' => $plan->id, 'provider' => 'fake', 'provider_price_id' => 'price_fake']);

        $topUp = TopUp::create(['code' => 'credits-100', 'name' => '100 credits', 'is_active' => true]);
        TopUpProviderPrice::create(['top_up_id' => $topUp->id, 'provider' => 'fake', 'provider_price_id' => 'price_topup']);
    }

    protected function tearDown(): void
    {
        $this->service->authorizeBillableUsing(null);
        $this->service->resolveBillableUsing(null);
        $this->service->resolveCheckoutUsing(null);

        parent::tearDown();
    }

    private function subscriptionFor(User $owner): CashierSubscription
    {
        return CashierSubscription::create([
            'user_id' => $owner->id, 'type' => 'default', 'stripe_id' => 'local_'.uniqid(),
            'stripe_status' => 'active', 'stripe_price' => 'local:pro',
        ]);
    }

    private function checkoutPayload(): array
    {
        return [
            'plan_code' => 'pro', 'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no', 'provider' => 'fake',
        ];
    }

    private function topUpPayload(): array
    {
        return [
            'top_up_code' => 'credits-100', 'success_url' => 'https://x.test/ok',
            'cancel_url' => 'https://x.test/no', 'provider' => 'fake',
        ];
    }

    /** Records the billable the checkout hook is handed. */
    private function captureCheckoutUser(): \stdClass
    {
        $seen = new \stdClass;
        $seen->user = null;
        $this->service->resolveCheckoutUsing(function (CheckoutContext $c) use ($seen) {
            $seen->user = $c->user;
        });

        return $seen;
    }

    // ── resolveBillableUsing ────────────────────────────────────────────────

    public function test_resolver_names_the_billable_when_the_client_names_none(): void
    {
        $me = User::factory()->create();
        $team = User::factory()->create();
        $seen = $this->captureCheckoutUser();

        $this->service->resolveBillableUsing(fn (Request $request) => $team);

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload())
            ->assertRedirect('https://fake.test/subscription');

        $this->assertTrue($seen->user->is($team));
    }

    public function test_resolver_billable_is_used_for_top_ups_and_the_api(): void
    {
        $me = User::factory()->create();
        $team = User::factory()->create();
        $seen = $this->captureCheckoutUser();

        $this->service->resolveBillableUsing(fn () => $team);

        $this->actingAs($me)->postJson('/api/subkit/top-ups/checkout', $this->topUpPayload())->assertOk();
        $this->assertTrue($seen->user->is($team));

        $seen->user = null;
        $this->actingAs($me)->postJson('/api/subkit/checkout', $this->checkoutPayload())->assertOk();
        $this->assertTrue($seen->user->is($team));
    }

    public function test_resolver_receives_the_request(): void
    {
        $team = User::factory()->create();
        $received = null;

        $this->service->resolveBillableUsing(function (Request $request) use ($team, &$received) {
            $received = $request->header('X-Team');

            return $team;
        });

        $this->actingAs(User::factory()->create())
            ->post('/subkit/checkout', $this->checkoutPayload(), ['X-Team' => 'acme'])
            ->assertRedirect();

        $this->assertSame('acme', $received);
    }

    public function test_resolver_returning_null_is_forbidden_for_a_user_and_unauthorized_for_a_guest(): void
    {
        $this->service->resolveBillableUsing(fn () => null);

        $this->post('/subkit/checkout', $this->checkoutPayload())->assertUnauthorized();

        $this->actingAs(User::factory()->create())->post('/subkit/checkout', $this->checkoutPayload())->assertForbidden();
    }

    public function test_resolver_can_identify_a_request_without_an_authenticated_user(): void
    {
        $tenant = User::factory()->create();
        $seen = $this->captureCheckoutUser();

        // A custom authentication: no $request->user(), the host maps its own credential to a billable.
        $this->service->resolveBillableUsing(
            fn (Request $request) => $request->header('X-Tenant') ? User::find($request->header('X-Tenant')) : null
        );

        $this->post('/subkit/checkout', $this->checkoutPayload())->assertUnauthorized();

        $this->post('/subkit/checkout', $this->checkoutPayload(), ['X-Tenant' => (string) $tenant->id])
            ->assertRedirect('https://fake.test/subscription');

        $this->assertTrue($seen->user->is($tenant));
    }

    public function test_resolver_must_return_a_model_or_null(): void
    {
        $this->service->resolveBillableUsing(fn () => 'not-a-model');

        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);

        $this->actingAs(User::factory()->create())->post('/subkit/checkout', $this->checkoutPayload());
    }

    public function test_company_id_is_still_checked_when_a_resolver_is_registered(): void
    {
        $me = User::factory()->create();
        $team = User::factory()->create();
        $other = User::factory()->create();

        $this->service->resolveBillableUsing(fn () => $team);

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload() + ['company_id' => (string) $other->id])
            ->assertForbidden();

        // Naming the resolver's own billable is not client trust: the host already chose it.
        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload() + ['company_id' => (string) $team->id])
            ->assertRedirect();
    }

    // ── subscription actions under a resolver ───────────────────────────────

    public function test_resolver_billable_may_manage_its_own_subscriptions_but_nobody_elses(): void
    {
        $me = User::factory()->create();
        $team = User::factory()->create();
        $teamSub = $this->subscriptionFor($team);
        $strangerSub = $this->subscriptionFor(User::factory()->create());

        $this->service->resolveBillableUsing(fn () => $team);

        $this->actingAs($me)->post("/subkit/subscriptions/{$strangerSub->id}/cancel")->assertForbidden();
        $this->actingAs($me)->post("/subkit/subscriptions/{$teamSub->id}/cancel")->assertRedirect();

        $this->assertSame('active', $strangerSub->fresh()->stripe_status);
        $this->assertSame('canceled', $teamSub->fresh()->stripe_status);
    }

    // ── abilities ───────────────────────────────────────────────────────────

    public function test_rule_receives_the_ability_for_every_action(): void
    {
        $me = User::factory()->create();
        $team = User::factory()->create();
        $sub = $this->subscriptionFor($team);
        $abilities = [];

        $this->service->resolveBillableUsing(fn () => $team);
        $this->service->authorizeBillableUsing(function ($actor, $billable, $ability) use (&$abilities) {
            $abilities[] = $ability;

            return true;
        });

        $this->actingAs($me);
        $this->post('/subkit/checkout', $this->checkoutPayload());
        $this->post('/subkit/top-ups/checkout', $this->topUpPayload());
        $this->getJson('/api/subkit/subscriptions/user');
        $this->post("/subkit/subscriptions/{$sub->id}/cancel");
        $this->post("/subkit/subscriptions/{$sub->id}/resume");
        $this->post('/subkit/billing-portal', ['subscription_id' => $sub->id, 'return_url' => 'https://x.test/back']);

        $this->assertSame(['checkout', 'top_up', 'view', 'cancel', 'resume', 'portal'], $abilities);
    }

    public function test_rule_can_grant_some_abilities_and_withhold_others(): void
    {
        $viewer = User::factory()->create();
        $team = User::factory()->create();
        $sub = $this->subscriptionFor($team);

        $this->service->resolveBillableUsing(fn () => $team);
        // A team "viewer": may look, may not change anything.
        $this->service->authorizeBillableUsing(fn ($actor, $billable, $ability) => $ability === 'view');

        $this->actingAs($viewer);
        $this->getJson('/api/subkit/subscriptions/user')->assertOk()->assertJsonCount(1);
        $this->post('/subkit/checkout', $this->checkoutPayload())->assertForbidden();
        $this->post('/subkit/top-ups/checkout', $this->topUpPayload())->assertForbidden();
        $this->post("/subkit/subscriptions/{$sub->id}/cancel")->assertForbidden();
        $this->post('/subkit/billing-portal', ['subscription_id' => $sub->id, 'return_url' => 'https://x.test/back'])
            ->assertForbidden();

        $this->assertSame('active', $sub->fresh()->stripe_status);
    }

    public function test_acting_for_yourself_never_asks_the_rule(): void
    {
        $me = User::factory()->create();
        $sub = $this->subscriptionFor($me);

        $this->service->authorizeBillableUsing(fn () => false);

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload())->assertRedirect();
        $this->actingAs($me)->post("/subkit/subscriptions/{$sub->id}/cancel")->assertRedirect();
    }

    public function test_a_rule_may_declare_only_the_parameters_it_needs(): void
    {
        $me = User::factory()->create();
        $company = User::factory()->create();

        $this->service->authorizeBillableUsing(fn ($actor, $billable) => $billable->is($company));

        $this->actingAs($me)->post('/subkit/checkout', $this->checkoutPayload() + ['company_id' => (string) $company->id])
            ->assertRedirect();
    }
}
