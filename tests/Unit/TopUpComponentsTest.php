<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use SubKit\Models\TopUp;
use SubKit\Models\TopUpProviderPrice;
use SubKit\View\Components\BaseSubscriptionComponent;
use SubKit\View\Components\TopUp as TopUpComponent;
use SubKit\View\Components\TopUpList;
use Tests\TestCase;

class TopUpComponentsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    private function topUp(string $code, int $amount, string $provider = 'stripe', bool $active = true, ?string $currency = null): TopUp
    {
        $topUp = TopUp::create([
            'code' => $code, 'name' => strtoupper($code), 'description' => "About {$code}",
            'amount' => $amount, 'currency' => $currency, 'is_active' => $active,
        ]);
        TopUpProviderPrice::create(['top_up_id' => $topUp->id, 'provider' => $provider, 'provider_price_id' => "price_{$code}"]);

        return $topUp;
    }

    // ── model ───────────────────────────────────────────────────────────────

    public function test_formatted_price_uses_default_symbol_or_the_currency_code(): void
    {
        $this->assertSame('$99.00', $this->topUp('a', 9900)->formatted_price);
        $this->assertSame('EUR 5.50', $this->topUp('b', 550, currency: 'eur')->formatted_price);
        $this->assertNull(TopUp::create(['code' => 'c', 'name' => 'C'])->formatted_price);
    }

    public function test_available_scope_requires_active_and_a_price_for_the_provider(): void
    {
        $this->topUp('stripe-one', 100);
        $this->topUp('paypal-only', 100, provider: 'paypal');
        $this->topUp('inactive', 100, active: false);
        TopUp::create(['code' => 'no-price', 'name' => 'No price', 'is_active' => true]);

        $this->assertSame(['stripe-one'], TopUp::available('stripe')->pluck('code')->all());
        $this->assertSame(['paypal-only'], TopUp::available('paypal')->pluck('code')->all());
    }

    public function test_deleting_a_top_up_removes_its_provider_prices(): void
    {
        $topUp = $this->topUp('gone', 100);

        $topUp->delete();

        $this->assertSame(0, TopUpProviderPrice::count());
    }

    // ── single snippet ──────────────────────────────────────────────────────

    public function test_single_snippet_renders_for_a_guest_with_a_register_link(): void
    {
        $this->topUp('credits-100', 9900);

        $html = Blade::render('<x-subkit::top-up code="credits-100" guest-redirect-url="/join" />');

        $this->assertStringContainsString('CREDITS-100', $html);
        $this->assertStringContainsString('$99.00', $html);
        $this->assertStringContainsString(url('/join'), $html);
        $this->assertStringNotContainsString('name="top_up_code"', $html);
    }

    public function test_single_snippet_posts_only_names_for_an_authenticated_user(): void
    {
        $this->topUp('credits-100', 9900);
        $this->actingAs(User::factory()->create());

        $html = Blade::render('<x-subkit::top-up code="credits-100" success-url="/ok" cancel-url="/no" company-id="7" />');

        $this->assertStringContainsString(route('subkit.top-ups.checkout.redirect'), $html);
        $this->assertStringContainsString('name="top_up_code" value="credits-100"', $html);
        $this->assertStringContainsString('name="company_id" value="7"', $html);
        $this->assertStringContainsString(url('/ok'), $html);
        $this->assertStringNotContainsString('price_credits-100', $html, 'provider price IDs must never reach the page');
        $this->assertStringNotContainsString('name="amount"', $html);
    }

    public function test_single_snippet_renders_nothing_for_unavailable_top_ups(): void
    {
        $this->topUp('inactive', 100, active: false);
        $this->topUp('paypal-only', 100, provider: 'paypal');

        $this->assertSame('', trim(Blade::render('<x-subkit::top-up code="inactive" />')));
        $this->assertSame('', trim(Blade::render('<x-subkit::top-up code="paypal-only" />')));
        $this->assertSame('', trim(Blade::render('<x-subkit::top-up code="missing" />')));
    }

    public function test_provider_prop_selects_the_provider(): void
    {
        $this->topUp('paypal-only', 100, provider: 'paypal');

        $html = Blade::render('<x-subkit::top-up code="paypal-only" provider="paypal" />');

        $this->assertStringContainsString('PAYPAL-ONLY', $html);
    }

    // ── list snippet ────────────────────────────────────────────────────────

    public function test_list_snippet_renders_a_radio_group_with_the_first_preselected(): void
    {
        $this->topUp('small', 1000);
        $this->topUp('large', 5000);
        $this->actingAs(User::factory()->create());

        $html = Blade::render('<x-subkit::top-up-list success-url="/ok" cancel-url="/no" />');

        $this->assertSame(2, substr_count($html, 'type="radio" name="top_up_code"'));
        $this->assertMatchesRegularExpression('/value="small"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="large"[^>]*checked/', $html);
        $this->assertStringContainsString('$10.00', $html);
        $this->assertStringContainsString('$50.00', $html);
        $this->assertSame(1, substr_count($html, '<button'));
    }

    public function test_list_snippet_can_be_limited_to_codes(): void
    {
        $this->topUp('a', 100);
        $this->topUp('b', 200);
        $this->topUp('c', 300);

        $byArray = new TopUpList(codes: ['a', 'c']);
        $byString = new TopUpList(codes: 'b, c');

        $this->assertSame(['a', 'c'], $byArray->topUps->pluck('code')->all());
        $this->assertSame(['b', 'c'], $byString->topUps->pluck('code')->all());
    }

    public function test_list_snippet_renders_nothing_when_nothing_is_available(): void
    {
        $this->assertSame('', trim(Blade::render('<x-subkit::top-up-list />')));
    }

    public function test_list_snippet_shows_a_plain_list_and_register_link_to_guests(): void
    {
        $this->topUp('small', 1000);

        $html = Blade::render('<x-subkit::top-up-list guest-redirect-url="/join" />');

        $this->assertStringNotContainsString('type="radio"', $html);
        $this->assertStringContainsString('SMALL', $html);
        $this->assertStringContainsString(url('/join'), $html);
    }

    // ── themes ──────────────────────────────────────────────────────────────

    public function test_every_built_in_theme_renders_both_snippets_and_unknown_themes_fall_back(): void
    {
        $this->topUp('credits-100', 9900);
        $this->actingAs(User::factory()->create());

        foreach (array_keys(BaseSubscriptionComponent::availableThemes()) + [99 => 'does-not-exist'] as $theme) {
            $single = Blade::render('<x-subkit::top-up code="credits-100" theme="'.$theme.'" success-url="/ok" />');
            $list = Blade::render('<x-subkit::top-up-list theme="'.$theme.'" success-url="/ok" />');

            $this->assertStringContainsString('$99.00', $single, "single / {$theme}");
            $this->assertStringContainsString('type="radio"', $list, "list / {$theme}");
        }

        $this->assertInstanceOf(TopUpComponent::class, new TopUpComponent(code: 'x'));
    }
}
