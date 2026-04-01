<?php

namespace Tests\Unit;

use SubKit\Models\PlanSet;
use SubKit\View\Components\PricingTable;
use Tests\TestCase;

/**
 * Verifies that URLs configured on a PlanSet (via Filament admin) actually
 * reach the PricingTable component's public properties.
 *
 * Root cause that prompted these tests: public readonly props on PricingTable
 * were overriding the resolved values from getThemeData() due to Blade's
 * component merge order.
 */
class PricingTableUrlPropsTest extends TestCase
{
    private function makeSet(array $attributes = []): PlanSet
    {
        static $i = 0;
        $i++;

        return PlanSet::create(array_merge([
            'name'      => "Set {$i}",
            'code'      => "set-{$i}",
            'is_active' => true,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // free_url
    // -------------------------------------------------------------------------

    public function test_free_url_from_plan_set_reaches_component(): void
    {
        $set = $this->makeSet(['free_url' => '/signup']);

        $component = new PricingTable(set: $set->code);

        $this->assertEquals(url('/signup'), $component->freeUrl);
    }

    public function test_free_url_route_name_resolved_to_full_url(): void
    {
        \Illuminate\Support\Facades\Route::get('/register', fn () => '')->name('test.register');
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

        $set = $this->makeSet(['free_url' => 'test.register']);

        $component = new PricingTable(set: $set->code);

        $this->assertEquals(route('test.register'), $component->freeUrl);
    }

    public function test_free_url_empty_in_plan_set_returns_fallback(): void
    {
        $set = $this->makeSet(['free_url' => null]);

        $component = new PricingTable(set: $set->code);

        $this->assertEquals('#', $component->freeUrl);
    }

    // -------------------------------------------------------------------------
    // success_url
    // -------------------------------------------------------------------------

    public function test_success_url_from_plan_set_reaches_component(): void
    {
        $set = $this->makeSet(['success_url' => '/thanks']);

        $component = new PricingTable(set: $set->code);

        $this->assertEquals(url('/thanks'), $component->successUrl);
    }

    public function test_success_url_with_stripe_placeholder_preserved(): void
    {
        $set = $this->makeSet(['success_url' => '/thanks?session_id={CHECKOUT_SESSION_ID}']);

        $component = new PricingTable(set: $set->code);

        $this->assertStringContainsString('{CHECKOUT_SESSION_ID}', $component->successUrl);
        $this->assertStringStartsWith('http', $component->successUrl);
    }

    // -------------------------------------------------------------------------
    // cancel_url
    // -------------------------------------------------------------------------

    public function test_cancel_url_from_plan_set_reaches_component(): void
    {
        $set = $this->makeSet(['cancel_url' => '/pricing']);

        $component = new PricingTable(set: $set->code);

        $this->assertEquals(url('/pricing'), $component->cancelUrl);
    }

    // -------------------------------------------------------------------------
    // Component prop takes priority over PlanSet value
    // -------------------------------------------------------------------------

    public function test_component_prop_overrides_plan_set_free_url(): void
    {
        $set = $this->makeSet(['free_url' => '/from-db']);

        $component = new PricingTable(set: $set->code, freeUrl: '/from-prop');

        $this->assertEquals(url('/from-prop'), $component->freeUrl);
    }
}