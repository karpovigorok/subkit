<?php

namespace Tests\Unit;

use SubKit\Models\Plan;
use SubKit\Models\PlanSet;
use SubKit\View\Components\PricingTable;
use Tests\TestCase;

class PricingTablePrivatePlanTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePlan(bool $isPrivate = false, bool $isActive = true): Plan
    {
        static $i = 0;
        $i++;

        return Plan::create([
            'code' => "plan-priv-{$i}",
            'name' => "Plan {$i}",
            'interval' => 'monthly',
            'is_active' => $isActive,
            'is_private' => $isPrivate,
            'version' => 1,
        ]);
    }

    private function makeSet(): PlanSet
    {
        static $j = 0;
        $j++;

        return PlanSet::create([
            'name' => "Set {$j}",
            'code' => "set-priv-{$j}",
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Without a PlanSet (global plan list)
    // -------------------------------------------------------------------------

    public function test_private_plans_excluded_from_global_list(): void
    {
        $public = $this->makePlan(isPrivate: false);
        $private = $this->makePlan(isPrivate: true);

        $component = new PricingTable;

        $ids = $component->plans->pluck('id')->all();

        $this->assertContains($public->id, $ids);
        $this->assertNotContains($private->id, $ids);
    }

    public function test_private_active_plan_excluded_from_global_list(): void
    {
        // is_active=true should not override is_private=true
        $private = $this->makePlan(isPrivate: true, isActive: true);

        $component = new PricingTable;

        $this->assertNotContains($private->id, $component->plans->pluck('id')->all());
    }

    public function test_only_private_plans_in_global_list_yields_empty(): void
    {
        $this->makePlan(isPrivate: true);

        $component = new PricingTable;

        $this->assertCount(0, $component->plans);
    }

    // -------------------------------------------------------------------------
    // With a PlanSet
    // -------------------------------------------------------------------------

    public function test_private_plans_excluded_from_set(): void
    {
        $set = $this->makeSet();
        $public = $this->makePlan(isPrivate: false);
        $private = $this->makePlan(isPrivate: true);

        $set->plans()->attach($public->id, ['sort_order' => 1, 'is_highlighted' => false]);
        $set->plans()->attach($private->id, ['sort_order' => 2, 'is_highlighted' => false]);

        $component = new PricingTable(set: $set->code);

        $ids = $component->plans->pluck('id')->all();

        $this->assertContains($public->id, $ids);
        $this->assertNotContains($private->id, $ids);
    }

    public function test_only_private_plans_in_set_yields_empty(): void
    {
        $set = $this->makeSet();
        $private = $this->makePlan(isPrivate: true);

        $set->plans()->attach($private->id, ['sort_order' => 1, 'is_highlighted' => false]);

        $component = new PricingTable(set: $set->code);

        $this->assertCount(0, $component->plans);
    }

    // -------------------------------------------------------------------------
    // highlighted map must not reference private plan IDs
    // -------------------------------------------------------------------------

    public function test_highlighted_map_excludes_private_plans(): void
    {
        $set = $this->makeSet();
        $public = $this->makePlan(isPrivate: false);
        $private = $this->makePlan(isPrivate: true);

        $set->plans()->attach($public->id, ['sort_order' => 1, 'is_highlighted' => true]);
        $set->plans()->attach($private->id, ['sort_order' => 2, 'is_highlighted' => true]);

        $component = new PricingTable(set: $set->code);

        $this->assertArrayHasKey($public->id, $component->highlighted);
        $this->assertArrayNotHasKey($private->id, $component->highlighted);
    }

    public function test_highlighted_flag_preserved_for_public_plan(): void
    {
        $set = $this->makeSet();
        $plan = $this->makePlan(isPrivate: false);

        $set->plans()->attach($plan->id, ['sort_order' => 1, 'is_highlighted' => true]);

        $component = new PricingTable(set: $set->code);

        $this->assertTrue($component->highlighted[$plan->id]);
    }
}
