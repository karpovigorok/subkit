<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription as CashierSubscription;
use SubKit\Models\Plan;
use SubKit\Models\PlanAssignment;
use SubKit\Services\SubscriptionService;
use SubKit\View\Components\PersonalOffers;
use Tests\TestCase;

class FreePlanTest extends TestCase
{
    private static int $seq = 0;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('subkit.billable_model', User::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeFreePlan(?string $code = null): Plan
    {
        $i = ++self::$seq;

        return Plan::create([
            'code' => $code ?? "free-plan-{$i}",
            'name' => "Free Plan {$i}",
            'interval' => 'monthly',
            'price' => null,
            'is_active' => true,
            'is_private' => true,
            'version' => 1,
        ]);
    }

    private function assignPlan(Plan $plan, User $user): void
    {
        PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => User::class,
            'assignable_id' => $user->id,
        ]);
    }

    private function localSubscription(User $user, string $planCode, string $status = 'active'): CashierSubscription
    {
        return CashierSubscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'local_'.uniqid(),
            'stripe_status' => $status,
            'stripe_price' => 'local:'.$planCode,
        ]);
    }

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    // -------------------------------------------------------------------------
    // checkout() — $0 plan with no Stripe price
    // -------------------------------------------------------------------------

    public function test_checkout_creates_active_subscription_for_free_plan(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();

        $this->service()->checkout(
            planCode: $plan->code,
            userId: (string) $user->id,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'stripe_status' => 'active',
        ]);
    }

    public function test_checkout_stores_plan_code_in_stripe_price(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan('my-demo-plan');

        $this->service()->checkout(
            planCode: $plan->code,
            userId: (string) $user->id,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'stripe_price' => 'local:my-demo-plan',
        ]);
    }

    public function test_checkout_sets_local_prefix_on_stripe_id(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();

        $this->service()->checkout(
            planCode: $plan->code,
            userId: (string) $user->id,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $sub = CashierSubscription::where('user_id', $user->id)->first();
        $this->assertStringStartsWith('local_', $sub->stripe_id);
    }

    public function test_checkout_returns_directly_subscribed_true(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();

        $result = $this->service()->checkout(
            planCode: $plan->code,
            userId: (string) $user->id,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $this->assertTrue($result->directlySubscribed);
        $this->assertSame('https://example.com/success', $result->url);
    }

    // -------------------------------------------------------------------------
    // cancel() — local subscription
    // -------------------------------------------------------------------------

    public function test_cancel_local_subscription_sets_canceled_status(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();
        $sub = $this->localSubscription($user, $plan->code);

        $this->service()->cancel($sub->id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $sub->id,
            'stripe_status' => 'canceled',
        ]);
    }

    public function test_cancel_local_subscription_sets_ends_at(): void
    {
        Carbon::setTestNow('2026-07-17 12:00:00');

        $user = $this->makeUser();
        $plan = $this->makeFreePlan();
        $sub = $this->localSubscription($user, $plan->code);

        $this->service()->cancel($sub->id);

        $this->assertNotNull($sub->fresh()->ends_at);

        Carbon::setTestNow();
    }

    public function test_cancel_does_not_affect_real_stripe_subscription(): void
    {
        // A non-local subscription should NOT be silently "canceled" locally —
        // it must reach the Stripe provider (which would throw here since there's
        // no valid Stripe key in tests, confirming the provider was invoked).
        $user = $this->makeUser();
        $sub = CashierSubscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_real_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_real_abc',
        ]);

        $this->expectException(\Throwable::class);
        $this->service()->cancel($sub->id);
    }

    // -------------------------------------------------------------------------
    // resume() — local subscription
    // -------------------------------------------------------------------------

    public function test_resume_local_subscription_restores_active_status(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();
        $sub = $this->localSubscription($user, $plan->code);
        $sub->update(['stripe_status' => 'canceled', 'ends_at' => now()]);

        $this->service()->resume($sub->id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $sub->id,
            'stripe_status' => 'active',
        ]);
    }

    public function test_resume_local_subscription_clears_ends_at(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();
        $sub = $this->localSubscription($user, $plan->code);
        $sub->update(['stripe_status' => 'canceled', 'ends_at' => now()]);

        $this->service()->resume($sub->id);

        $this->assertNull($sub->fresh()->ends_at);
    }

    // -------------------------------------------------------------------------
    // PersonalOffers filtering — local subscriptions
    // -------------------------------------------------------------------------

    public function test_local_plan_code_subscription_hides_offer(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();
        $this->assignPlan($plan, $user);

        $this->localSubscription($user, $plan->code, 'active');

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertTrue($component->offers->isEmpty());
    }

    public function test_null_stripe_price_subscription_hides_no_stripe_price_plan(): void
    {
        // Legacy subscriptions created before the local:{code} tracking was added.
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();
        $this->assignPlan($plan, $user);

        CashierSubscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'local_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => null,
        ]);

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertTrue($component->offers->isEmpty());
    }

    public function test_canceled_local_subscription_shows_offer_again(): void
    {
        $user = $this->makeUser();
        $plan = $this->makeFreePlan();
        $this->assignPlan($plan, $user);

        $this->localSubscription($user, $plan->code, 'canceled');

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertCount(1, $component->offers);
    }
}
