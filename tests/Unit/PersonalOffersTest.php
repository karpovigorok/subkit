<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\View\View;
use Laravel\Cashier\Subscription as CashierSubscription;
use SubKit\Models\Plan;
use SubKit\Models\PlanAssignment;
use SubKit\Models\PlanProviderPrice;
use SubKit\View\Components\PersonalOffers;
use Tests\TestCase;

class PersonalOffersTest extends TestCase
{
    private static int $planSeq = 0;

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

    private function makePlan(bool $isPrivate = true, ?string $stripePrice = null): Plan
    {
        $i = ++self::$planSeq;
        $plan = Plan::create([
            'code' => "offers-plan-{$i}",
            'name' => "Offer Plan {$i}",
            'interval' => 'monthly',
            'is_active' => true,
            'is_private' => $isPrivate,
            'version' => 1,
        ]);

        if ($stripePrice) {
            PlanProviderPrice::create([
                'plan_id' => $plan->id,
                'provider' => 'stripe',
                'provider_price_id' => $stripePrice,
            ]);
        }

        return $plan;
    }

    private function assignPlan(Plan $plan, User $user): PlanAssignment
    {
        return PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => User::class,
            'assignable_id' => $user->id,
        ]);
    }

    private function subscribeTo(User $user, string $stripePrice, string $status = 'active'): CashierSubscription
    {
        return CashierSubscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_test_'.uniqid(),
            'stripe_status' => $status,
            'stripe_price' => $stripePrice,
        ]);
    }

    // -------------------------------------------------------------------------
    // 1. scopePublic excludes private plans
    // -------------------------------------------------------------------------

    public function test_private_plan_is_excluded_from_scope_public(): void
    {
        $public = $this->makePlan(isPrivate: false);
        $private = $this->makePlan(isPrivate: true);

        $ids = Plan::public()->pluck('id');

        $this->assertContains($public->id, $ids);
        $this->assertNotContains($private->id, $ids);
    }

    // -------------------------------------------------------------------------
    // 2. Renders nothing when user has no assigned private plans
    // -------------------------------------------------------------------------

    public function test_renders_nothing_when_user_has_no_assigned_plans(): void
    {
        $user = $this->makeUser();

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertTrue($component->offers->isEmpty());
        $this->assertSame('', $component->render());
    }

    public function test_renders_nothing_when_billable_model_not_configured(): void
    {
        config()->set('subkit.billable_model', null);

        $user = $this->makeUser();
        $plan = $this->makePlan(isPrivate: true);
        $this->assignPlan($plan, $user);

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertTrue($component->offers->isEmpty());
        $this->assertSame('', $component->render());
    }

    public function test_renders_nothing_when_user_cannot_be_resolved(): void
    {
        $component = new PersonalOffers(userId: '99999');

        $this->assertTrue($component->offers->isEmpty());
        $this->assertSame('', $component->render());
    }

    // -------------------------------------------------------------------------
    // 3. Renders plan details and "Claim Offer" when an offer is assigned
    // -------------------------------------------------------------------------

    public function test_renders_assigned_plan_in_offers_collection(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(isPrivate: true);
        $this->assignPlan($plan, $user);

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertCount(1, $component->offers);
        $this->assertEquals($plan->id, $component->offers->first()->id);
    }

    public function test_renders_plan_name_and_claim_offer_button(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlan(isPrivate: true);
        $this->assignPlan($plan, $user);

        $component = new PersonalOffers(userId: (string) $user->id);

        $rendered = $component->render();
        $this->assertInstanceOf(View::class, $rendered);

        $html = $rendered->render();
        $this->assertStringContainsString($plan->name, $html);
        $this->assertStringContainsString('Claim Offer', $html);
        $this->assertStringContainsString('Personal Offer', $html);
    }

    public function test_only_assigned_plans_appear_not_all_private_plans(): void
    {
        $user = $this->makeUser();
        $assigned = $this->makePlan(isPrivate: true);
        $notAssigned = $this->makePlan(isPrivate: true);

        $this->assignPlan($assigned, $user);

        $component = new PersonalOffers(userId: (string) $user->id);

        $ids = $component->offers->pluck('id');
        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($notAssigned->id, $ids);
    }

    // -------------------------------------------------------------------------
    // 4. Already-subscribed plans are excluded from offers
    // -------------------------------------------------------------------------

    public function test_active_subscription_removes_plan_from_offers(): void
    {
        $user = $this->makeUser();
        $price = 'price_demo_active';
        $plan = $this->makePlan(isPrivate: true, stripePrice: $price);
        $this->assignPlan($plan, $user);
        $this->subscribeTo($user, $price, 'active');

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertTrue($component->offers->isEmpty());
        $this->assertSame('', $component->render());
    }

    public function test_trialing_subscription_removes_plan_from_offers(): void
    {
        $user = $this->makeUser();
        $price = 'price_demo_trial';
        $plan = $this->makePlan(isPrivate: true, stripePrice: $price);
        $this->assignPlan($plan, $user);
        $this->subscribeTo($user, $price, 'trialing');

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertTrue($component->offers->isEmpty());
        $this->assertSame('', $component->render());
    }

    public function test_canceled_subscription_does_not_remove_plan_from_offers(): void
    {
        // A user whose subscription lapsed should see the offer again
        $user = $this->makeUser();
        $price = 'price_demo_canceled';
        $plan = $this->makePlan(isPrivate: true, stripePrice: $price);
        $this->assignPlan($plan, $user);
        $this->subscribeTo($user, $price, 'canceled');

        $component = new PersonalOffers(userId: (string) $user->id);

        $this->assertCount(1, $component->offers);
    }

    public function test_unsubscribed_plan_remains_in_offers_despite_other_active_subscription(): void
    {
        // User is subscribed to plan A but not plan B — plan B's offer should still appear
        $user = $this->makeUser();
        $priceA = 'price_demo_a';
        $planA = $this->makePlan(isPrivate: true, stripePrice: $priceA);
        $planB = $this->makePlan(isPrivate: true);

        $this->assignPlan($planA, $user);
        $this->assignPlan($planB, $user);
        $this->subscribeTo($user, $priceA, 'active');

        $component = new PersonalOffers(userId: (string) $user->id);

        $ids = $component->offers->pluck('id');
        $this->assertNotContains($planA->id, $ids);
        $this->assertContains($planB->id, $ids);
    }
}
