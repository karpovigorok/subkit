<?php

namespace Tests\Unit;

use SubKit\Models\Plan;
use SubKit\Models\PlanSet;
use SubKit\View\Components\PricingTable;
use Tests\TestCase;

class PricingTableInactivePlanTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePlan(bool $isActive): Plan
    {
        static $i = 0;
        $i++;

        return Plan::create([
            'code' => "plan-{$i}",
            'name' => "Plan {$i}",
            'interval' => 'monthly',
            'is_active' => $isActive,
            'version' => 1,
        ]);
    }

    private function makeSet(): PlanSet
    {
        static $j = 0;
        $j++;

        return PlanSet::create([
            'name' => "Set {$j}",
            'code' => "set-{$j}",
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Without a PlanSet (global plan list)
    // -------------------------------------------------------------------------

    public function test_inactive_plans_excluded_from_global_list(): void
    {
        $active = $this->makePlan(isActive: true);
        $inactive = $this->makePlan(isActive: false);

        $component = new PricingTable;

        $ids = $component->plans->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    // -------------------------------------------------------------------------
    // With a PlanSet
    // -------------------------------------------------------------------------

    public function test_inactive_plans_excluded_from_set(): void
    {
        $set = $this->makeSet();
        $active = $this->makePlan(isActive: true);
        $inactive = $this->makePlan(isActive: false);

        $set->plans()->attach($active->id, ['sort_order' => 1, 'is_highlighted' => false]);
        $set->plans()->attach($inactive->id, ['sort_order' => 2, 'is_highlighted' => false]);

        $component = new PricingTable(set: $set->code);

        $ids = $component->plans->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_only_active_plans_from_set_when_all_inactive(): void
    {
        $set = $this->makeSet();
        $inactive = $this->makePlan(isActive: false);

        $set->plans()->attach($inactive->id, ['sort_order' => 1, 'is_highlighted' => false]);

        $component = new PricingTable(set: $set->code);

        $this->assertCount(0, $component->plans);
    }

    // -------------------------------------------------------------------------
    // highlighted map must not reference inactive plan IDs
    // -------------------------------------------------------------------------

    public function test_highlighted_map_excludes_inactive_plans(): void
    {
        $set = $this->makeSet();
        $active = $this->makePlan(isActive: true);
        $inactive = $this->makePlan(isActive: false);

        $set->plans()->attach($active->id, ['sort_order' => 1, 'is_highlighted' => true]);
        $set->plans()->attach($inactive->id, ['sort_order' => 2, 'is_highlighted' => true]);

        $component = new PricingTable(set: $set->code);

        $this->assertArrayHasKey($active->id, $component->highlighted);
        $this->assertArrayNotHasKey($inactive->id, $component->highlighted);
    }

    public function test_highlighted_flag_preserved_for_active_plan(): void
    {
        $set = $this->makeSet();
        $plan = $this->makePlan(isActive: true);

        $set->plans()->attach($plan->id, ['sort_order' => 1, 'is_highlighted' => true]);

        $component = new PricingTable(set: $set->code);

        $this->assertTrue($component->highlighted[$plan->id]);
    }
}
