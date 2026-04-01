<?php

namespace Tests\Unit;

use SubKit\Models\PlanSet;
use SubKit\View\Components\PricingTable;
use ReflectionMethod;
use Tests\TestCase;

class ResolveLabelsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function resolveLabels(?PlanSet $planSet, array $props = []): array
    {
        $component = new PricingTable(
            subscribeLabel: $props['subscribeLabel'] ?? null,
            freeLabel:      $props['freeLabel']      ?? null,
            guestLabel:     $props['guestLabel']     ?? null,
        );

        $method = new ReflectionMethod(PricingTable::class, 'resolveLabels');
        $method->setAccessible(true);

        return $method->invoke($component, $planSet);
    }

    private function makeSet(array $overrides = []): PlanSet
    {
        static $i = 0;
        $i++;

        return PlanSet::create(array_merge([
            'name'      => "Set {$i}",
            'code'      => "set-{$i}",
            'is_active' => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Translation fallback (tier 3)
    // -------------------------------------------------------------------------

    public function test_defaults_come_from_translations_when_no_prop_or_db(): void
    {
        $labels = $this->resolveLabels(null);

        $this->assertEquals(__('subkit::messages.buttons.get_started'),               $labels['subscribe']);
        $this->assertEquals(__('subkit::messages.buttons.get_started_free'),           $labels['free']);
        $this->assertEquals(__('subkit::messages.buttons.create_account_to_subscribe'), $labels['guest']);
    }

    public function test_interval_labels_always_come_from_translations(): void
    {
        $labels = $this->resolveLabels(null);

        $this->assertEquals(__('subkit::messages.pricing.toggle_monthly'), $labels['monthly']);
        $this->assertEquals(__('subkit::messages.pricing.toggle_yearly'),  $labels['yearly']);
    }

    // -------------------------------------------------------------------------
    // DB override (tier 2)
    // -------------------------------------------------------------------------

    public function test_db_subscribe_label_overrides_translation(): void
    {
        $set = $this->makeSet(['subscribe_label' => 'Start Now']);

        $labels = $this->resolveLabels($set);

        $this->assertEquals('Start Now', $labels['subscribe']);
    }

    public function test_db_free_label_overrides_translation(): void
    {
        $set = $this->makeSet(['free_label' => 'Try for Free']);

        $labels = $this->resolveLabels($set);

        $this->assertEquals('Try for Free', $labels['free']);
    }

    public function test_db_guest_label_overrides_translation(): void
    {
        $set = $this->makeSet(['guest_label' => 'Sign Up']);

        $labels = $this->resolveLabels($set);

        $this->assertEquals('Sign Up', $labels['guest']);
    }

    public function test_null_db_fields_fall_back_to_translations(): void
    {
        $set = $this->makeSet([
            'subscribe_label' => null,
            'free_label'      => null,
            'guest_label'     => null,
        ]);

        $labels = $this->resolveLabels($set);

        $this->assertEquals(__('subkit::messages.buttons.get_started'),                $labels['subscribe']);
        $this->assertEquals(__('subkit::messages.buttons.get_started_free'),            $labels['free']);
        $this->assertEquals(__('subkit::messages.buttons.create_account_to_subscribe'), $labels['guest']);
    }

    // -------------------------------------------------------------------------
    // Blade prop override (tier 1)
    // -------------------------------------------------------------------------

    public function test_prop_overrides_db_label(): void
    {
        $set = $this->makeSet(['subscribe_label' => 'DB Label']);

        $labels = $this->resolveLabels($set, ['subscribeLabel' => 'Prop Label']);

        $this->assertEquals('Prop Label', $labels['subscribe']);
    }

    public function test_prop_overrides_translation_when_no_db(): void
    {
        $labels = $this->resolveLabels(null, [
            'subscribeLabel' => 'Buy Now',
            'freeLabel'      => 'Start Free',
            'guestLabel'     => 'Join Us',
        ]);

        $this->assertEquals('Buy Now',    $labels['subscribe']);
        $this->assertEquals('Start Free', $labels['free']);
        $this->assertEquals('Join Us',    $labels['guest']);
    }

    public function test_prop_wins_over_both_db_and_translation(): void
    {
        $set = $this->makeSet([
            'subscribe_label' => 'DB Subscribe',
            'free_label'      => 'DB Free',
            'guest_label'     => 'DB Guest',
        ]);

        $labels = $this->resolveLabels($set, [
            'subscribeLabel' => 'Prop Subscribe',
            'freeLabel'      => 'Prop Free',
            'guestLabel'     => 'Prop Guest',
        ]);

        $this->assertEquals('Prop Subscribe', $labels['subscribe']);
        $this->assertEquals('Prop Free',      $labels['free']);
        $this->assertEquals('Prop Guest',     $labels['guest']);
    }
}
