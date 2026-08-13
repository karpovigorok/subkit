<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Subscription as CashierSubscription;
use SubKit\Models\Plan;
use SubKit\Models\PlanLimit;
use Tests\TestCase;

class HasCapabilitiesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('subkit.billable_model', User::class);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePlanWithLimits(string $code): Plan
    {
        $plan = Plan::create([
            'code'      => $code,
            'name'      => $code,
            'interval'  => 'monthly',
            'price'     => null,
            'is_active' => true,
            'version'   => 1,
        ]);

        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_locations', 'value' => '10',   'type' => 'int']);
        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'can_export',    'value' => 'true', 'type' => 'bool']);

        return $plan;
    }

    private function localSub(User $user, string $planCode): CashierSubscription
    {
        return CashierSubscription::create([
            'user_id'       => $user->id,
            'type'          => 'default',
            'stripe_id'     => 'local_' . uniqid(),
            'stripe_status' => 'active',
            'stripe_price'  => 'local:' . $planCode,
        ]);
    }

    // -------------------------------------------------------------------------

    public function test_returns_limits_for_local_subscription(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlanWithLimits('cap-plan-1');
        $this->localSub($user, $plan->code);

        $caps = $user->getCapabilities();

        $this->assertSame(10,   $caps['limits']['max_locations']);
        $this->assertSame(true, $caps['limits']['can_export']);
    }

    public function test_returns_empty_limits_when_no_subscription(): void
    {
        $user = $this->makeUser();

        $caps = $user->getCapabilities();

        $this->assertSame(['limits' => []], $caps);
    }

    public function test_returns_empty_limits_for_null_stripe_price(): void
    {
        $user = $this->makeUser();
        CashierSubscription::create([
            'user_id'       => $user->id,
            'type'          => 'default',
            'stripe_id'     => 'local_' . uniqid(),
            'stripe_status' => 'active',
            'stripe_price'  => null,
        ]);

        $caps = $user->getCapabilities();

        $this->assertSame(['limits' => []], $caps);
    }

    public function test_result_is_cached_on_second_call(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlanWithLimits('cap-plan-2');
        $this->localSub($user, $plan->code);

        $first  = $user->getCapabilities();
        $second = $user->getCapabilities();

        $this->assertSame($first, $second);
        $this->assertTrue(Cache::has("subkit.capabilities.{$user->id}"));
    }

    public function test_flush_cache_causes_re_resolution(): void
    {
        $user = $this->makeUser();
        $plan = $this->makePlanWithLimits('cap-plan-3');
        $this->localSub($user, $plan->code);

        $user->getCapabilities();
        $this->assertTrue(Cache::has("subkit.capabilities.{$user->id}"));

        $user->flushCapabilitiesCache();
        $this->assertFalse(Cache::has("subkit.capabilities.{$user->id}"));

        $caps = $user->getCapabilities();
        $this->assertSame(10, $caps['limits']['max_locations']);
    }
}
