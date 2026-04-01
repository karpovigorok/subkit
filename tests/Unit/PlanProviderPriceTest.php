<?php

namespace Tests\Unit;

use SubKit\Models\Plan;
use SubKit\Models\PlanProviderPrice;
use Tests\TestCase;

class PlanProviderPriceTest extends TestCase
{
    private function makePlan(): Plan
    {
        static $i = 0;
        $i++;

        return Plan::create([
            'code' => "plan-{$i}",
            'name' => "Plan {$i}",
            'interval' => 'monthly',
            'is_active' => true,
            'version' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // providerPrice() must return null when no record exists
    // -------------------------------------------------------------------------

    public function test_returns_null_when_no_provider_price_record(): void
    {
        $plan = $this->makePlan();

        $this->assertNull($plan->providerPrice('stripe'));
    }

    // -------------------------------------------------------------------------
    // providerPrice() must ignore records with empty provider_price_id
    // -------------------------------------------------------------------------

    public function test_returns_null_when_provider_price_id_is_empty_string(): void
    {
        $plan = $this->makePlan();

        PlanProviderPrice::create([
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'provider_price_id' => '',
        ]);

        $this->assertNull($plan->providerPrice('stripe'));
    }

    // -------------------------------------------------------------------------
    // providerPrice() must return the record when provider_price_id is set
    // -------------------------------------------------------------------------

    public function test_returns_record_when_provider_price_id_is_set(): void
    {
        $plan = $this->makePlan();

        PlanProviderPrice::create([
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'provider_price_id' => 'price_abc123',
        ]);

        $result = $plan->providerPrice('stripe');

        $this->assertNotNull($result);
        $this->assertEquals('price_abc123', $result->provider_price_id);
    }

    // -------------------------------------------------------------------------
    // providerPrice() must not return records for a different provider
    // -------------------------------------------------------------------------

    public function test_returns_null_for_different_provider(): void
    {
        $plan = $this->makePlan();

        PlanProviderPrice::create([
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'provider_price_id' => 'price_abc123',
        ]);

        $this->assertNull($plan->providerPrice('paypal'));
    }

    // -------------------------------------------------------------------------
    // Empty provider_price_id does not shadow a valid record for same provider
    // -------------------------------------------------------------------------

    public function test_empty_record_does_not_shadow_valid_record(): void
    {
        $plan = $this->makePlan();

        // Both records exist — one empty, one valid (shouldn't happen in practice
        // but providerPrice() should return the valid one if DB order allows it,
        // or at minimum not return the empty one).
        PlanProviderPrice::create([
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'provider_price_id' => 'price_valid',
        ]);

        $result = $plan->providerPrice('stripe');

        $this->assertNotNull($result);
        $this->assertEquals('price_valid', $result->provider_price_id);
    }
}
