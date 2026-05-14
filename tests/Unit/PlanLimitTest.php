<?php

namespace Tests\Unit;

use SubKit\Enums\SubscriptionInterval;
use SubKit\Models\Plan;
use SubKit\Models\PlanLimit;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    private function createPlan(): Plan
    {
        return Plan::create([
            'code' => 'test_plan_'.uniqid(),
            'name' => 'Test Plan',
            'interval' => SubscriptionInterval::Monthly,
            'version' => 1,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Relationship
    // -------------------------------------------------------------------------

    public function test_plan_has_many_limits(): void
    {
        $plan = $this->createPlan();

        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_users', 'value' => '5', 'type' => 'int']);
        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_maps', 'value' => '10', 'type' => 'int']);

        $this->assertCount(2, $plan->limits);
    }

    public function test_plan_limit_belongs_to_plan(): void
    {
        $plan = $this->createPlan();
        $limit = PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_users', 'value' => '5', 'type' => 'int']);

        $this->assertTrue($limit->plan->is($plan));
    }

    // -------------------------------------------------------------------------
    // Casting
    // -------------------------------------------------------------------------

    public function test_int_type_casts_to_integer(): void
    {
        $plan = $this->createPlan();
        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_locations', 'value' => '100', 'type' => 'int']);

        $plan->load('limits');

        $this->assertSame(100, $plan->getLimit('max_locations'));
        $this->assertIsInt($plan->getLimit('max_locations'));
    }

    public function test_bool_type_casts_true_string_to_true(): void
    {
        $plan = $this->createPlan();
        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'can_export', 'value' => 'true', 'type' => 'bool']);

        $plan->load('limits');

        $this->assertTrue($plan->getLimit('can_export'));
    }

    public function test_bool_type_casts_false_string_to_false(): void
    {
        $plan = $this->createPlan();
        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'can_export', 'value' => 'false', 'type' => 'bool']);

        $plan->load('limits');

        $this->assertFalse($plan->getLimit('can_export'));
    }

    public function test_string_type_returns_raw_string(): void
    {
        $plan = $this->createPlan();
        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'tier', 'value' => 'gold', 'type' => 'string']);

        $plan->load('limits');

        $this->assertSame('gold', $plan->getLimit('tier'));
        $this->assertIsString($plan->getLimit('tier'));
    }

    // -------------------------------------------------------------------------
    // getLimit() defaults
    // -------------------------------------------------------------------------

    public function test_get_limit_returns_null_for_missing_key(): void
    {
        $plan = $this->createPlan();

        $this->assertNull($plan->getLimit('nonexistent'));
    }

    public function test_get_limit_returns_provided_default_for_missing_key(): void
    {
        $plan = $this->createPlan();

        $this->assertSame(42, $plan->getLimit('nonexistent', 42));
        $this->assertSame('fallback', $plan->getLimit('nonexistent', 'fallback'));
    }

    // -------------------------------------------------------------------------
    // Database constraint
    // -------------------------------------------------------------------------

    public function test_key_must_be_unique_per_plan(): void
    {
        $plan = $this->createPlan();

        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_users', 'value' => '5', 'type' => 'int']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_users', 'value' => '10', 'type' => 'int']);
    }

    public function test_same_key_allowed_on_different_plans(): void
    {
        $planA = $this->createPlan();
        $planB = $this->createPlan();

        PlanLimit::create(['plan_id' => $planA->id, 'key' => 'max_users', 'value' => '5', 'type' => 'int']);
        PlanLimit::create(['plan_id' => $planB->id, 'key' => 'max_users', 'value' => '50', 'type' => 'int']);

        $planA->load('limits');
        $planB->load('limits');

        $this->assertSame(5, $planA->getLimit('max_users'));
        $this->assertSame(50, $planB->getLimit('max_users'));
    }

    // -------------------------------------------------------------------------
    // Cascade delete
    // -------------------------------------------------------------------------

    public function test_limits_deleted_when_plan_deleted(): void
    {
        $plan = $this->createPlan();
        $limitId = PlanLimit::create(['plan_id' => $plan->id, 'key' => 'max_users', 'value' => '5', 'type' => 'int'])->id;

        $plan->delete();

        $this->assertNull(PlanLimit::find($limitId));
    }
}
