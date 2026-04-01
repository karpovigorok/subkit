<?php

namespace Tests\Unit;

use SubKit\View\Components\PricingTable;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class ResolveUrlTest extends TestCase
{
    private function resolveUrl(?string $value, string $fallback = '#'): string
    {
        $component = new PricingTable();

        $method = new ReflectionMethod(PricingTable::class, 'resolveUrl');
        $method->setAccessible(true);

        return $method->invoke($component, $value, $fallback);
    }

    public function test_route_name_resolves_to_full_url(): void
    {
        Route::get('/dashboard', fn () => '')->name('test.dashboard');
        Route::getRoutes()->refreshNameLookups();

        $result = $this->resolveUrl('test.dashboard');

        $this->assertEquals(route('test.dashboard'), $result);
        $this->assertStringContainsString('/dashboard', $result);
    }

    public function test_relative_path_is_made_absolute(): void
    {
        $result = $this->resolveUrl('/thanks?utm=123');

        $this->assertStringStartsWith('http', $result);
        $this->assertStringEndsWith('/thanks?utm=123', $result);
    }

    public function test_relative_path_with_stripe_placeholder_made_absolute(): void
    {
        $result = $this->resolveUrl('/checkout-success?session_id={CHECKOUT_SESSION_ID}');

        $this->assertStringStartsWith('http', $result);
        $this->assertStringContainsString('{CHECKOUT_SESSION_ID}', $result);
    }

    public function test_full_url_with_utm_passes_through_unchanged(): void
    {
        $result = $this->resolveUrl('https://example.com/thanks?utm_source=fb&utm_medium=cpc');

        $this->assertEquals('https://example.com/thanks?utm_source=fb&utm_medium=cpc', $result);
    }

    public function test_null_returns_default_fallback(): void
    {
        $this->assertEquals('#', $this->resolveUrl(null));
    }

    public function test_empty_string_returns_default_fallback(): void
    {
        $this->assertEquals('#', $this->resolveUrl(''));
    }

    public function test_custom_fallback_returned_for_empty_value(): void
    {
        $this->assertEquals('/register', $this->resolveUrl(null, '/register'));
    }

    public function test_unknown_string_treated_as_raw_value_not_route(): void
    {
        $result = $this->resolveUrl('not-a-registered-route');

        $this->assertEquals('not-a-registered-route', $result);
    }

    public function test_single_quoted_route_name_resolves_correctly(): void
    {
        Route::get('/signup', fn () => '')->name('test.register');
        Route::getRoutes()->refreshNameLookups();

        $result = $this->resolveUrl("'test.register'");

        $this->assertEquals(route('test.register'), $result);
    }

    public function test_double_quoted_route_name_resolves_correctly(): void
    {
        Route::get('/login', fn () => '')->name('test.login');
        Route::getRoutes()->refreshNameLookups();

        $result = $this->resolveUrl('"test.login"');

        $this->assertEquals(route('test.login'), $result);
    }

    public function test_quoted_empty_string_returns_fallback(): void
    {
        $this->assertEquals('#', $this->resolveUrl("''"));
        $this->assertEquals('#', $this->resolveUrl('""'));
    }
}
