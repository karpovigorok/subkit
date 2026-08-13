<?php

namespace Tests\Unit;

use SubKit\Enums\SubscriptionInterval;
use SubKit\Models\Plan;
use SubKit\Models\PlanAssignment;
use Tests\TestCase;

class PrivatePlanTest extends TestCase
{
    private function createPlan(bool $isPrivate = false, bool $isActive = true): Plan
    {
        return Plan::create([
            'code' => 'plan_'.uniqid(),
            'name' => 'Test Plan',
            'interval' => SubscriptionInterval::Monthly,
            'version' => 1,
            'is_active' => $isActive,
            'is_private' => $isPrivate,
        ]);
    }

    // -------------------------------------------------------------------------
    // scopePublic
    // -------------------------------------------------------------------------

    public function test_scope_public_excludes_private_plans(): void
    {
        $public = $this->createPlan(isPrivate: false);
        $private = $this->createPlan(isPrivate: true);

        $results = Plan::public()->pluck('id');

        $this->assertContains($public->id, $results);
        $this->assertNotContains($private->id, $results);
    }

    public function test_scope_public_returns_all_non_private_plans(): void
    {
        $this->createPlan(isPrivate: false);
        $this->createPlan(isPrivate: false);
        $this->createPlan(isPrivate: true);

        $this->assertCount(2, Plan::public()->get());
    }

    // -------------------------------------------------------------------------
    // is_private cast
    // -------------------------------------------------------------------------

    public function test_is_private_defaults_to_false(): void
    {
        $plan = Plan::create([
            'code' => 'plan_'.uniqid(),
            'name' => 'Test',
            'interval' => SubscriptionInterval::Monthly,
            'version' => 1,
            'is_active' => true,
        ]);

        $this->assertFalse($plan->fresh()->is_private);
    }

    public function test_is_private_cast_to_boolean(): void
    {
        $plan = $this->createPlan(isPrivate: true);

        $this->assertTrue($plan->fresh()->is_private);
        $this->assertIsBool($plan->fresh()->is_private);
    }

    // -------------------------------------------------------------------------
    // Assignments relationship
    // -------------------------------------------------------------------------

    public function test_plan_has_many_assignments(): void
    {
        $plan = $this->createPlan(isPrivate: true);

        PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => 'App\\Models\\User',
            'assignable_id' => 1,
        ]);

        PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => 'App\\Models\\User',
            'assignable_id' => 2,
        ]);

        $this->assertCount(2, $plan->assignments);
    }

    public function test_assignment_belongs_to_plan(): void
    {
        $plan = $this->createPlan(isPrivate: true);

        $assignment = PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => 'App\\Models\\User',
            'assignable_id' => 1,
        ]);

        $this->assertTrue($assignment->plan->is($plan));
    }

    public function test_duplicate_assignment_is_rejected(): void
    {
        $plan = $this->createPlan(isPrivate: true);

        PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => 'App\\Models\\User',
            'assignable_id' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => 'App\\Models\\User',
            'assignable_id' => 1,
        ]);
    }

    public function test_same_assignable_can_be_assigned_different_plans(): void
    {
        $planA = $this->createPlan(isPrivate: true);
        $planB = $this->createPlan(isPrivate: true);

        PlanAssignment::create(['plan_id' => $planA->id, 'assignable_type' => 'App\\Models\\User', 'assignable_id' => 1]);
        PlanAssignment::create(['plan_id' => $planB->id, 'assignable_type' => 'App\\Models\\User', 'assignable_id' => 1]);

        $this->assertCount(1, $planA->assignments);
        $this->assertCount(1, $planB->assignments);
    }

    // -------------------------------------------------------------------------
    // Cascade delete
    // -------------------------------------------------------------------------

    public function test_assignments_deleted_when_plan_deleted(): void
    {
        $plan = $this->createPlan(isPrivate: true);

        $assignmentId = PlanAssignment::create([
            'plan_id' => $plan->id,
            'assignable_type' => 'App\\Models\\User',
            'assignable_id' => 1,
        ])->id;

        $plan->delete();

        $this->assertNull(PlanAssignment::find($assignmentId));
    }
}
