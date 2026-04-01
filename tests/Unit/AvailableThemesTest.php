<?php

namespace Tests\Unit;

use SubKit\View\Components\BaseSubscriptionComponent;
use Tests\TestCase;

class AvailableThemesTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Package themes
    // -------------------------------------------------------------------------

    public function test_built_in_themes_are_returned(): void
    {
        $themes = BaseSubscriptionComponent::availableThemes();

        $this->assertArrayHasKey('default', $themes);
        $this->assertArrayHasKey('dark', $themes);
    }

    public function test_labels_are_human_readable(): void
    {
        $themes = BaseSubscriptionComponent::availableThemes();

        $this->assertSame('Default', $themes['default']);
        $this->assertSame('Dark', $themes['dark']);
    }

    public function test_returns_associative_array_of_strings(): void
    {
        $themes = BaseSubscriptionComponent::availableThemes();

        $this->assertIsArray($themes);
        foreach ($themes as $key => $label) {
            $this->assertIsString($key);
            $this->assertIsString($label);
        }
    }

    // -------------------------------------------------------------------------
    // Validation contract: folder must contain pricing-table.blade.php
    // -------------------------------------------------------------------------

    public function test_folder_without_pricing_table_is_excluded(): void
    {
        $themesDir = realpath(__DIR__.'/../../resources/views/themes');
        $emptyDir = $themesDir.'/empty_theme_test';

        // Create a folder with no pricing-table.blade.php
        mkdir($emptyDir, 0755, true);

        try {
            $themes = BaseSubscriptionComponent::availableThemes();
            $this->assertArrayNotHasKey('empty_theme_test', $themes);
        } finally {
            rmdir($emptyDir);
        }
    }

    public function test_folder_with_pricing_table_is_included(): void
    {
        $themesDir = realpath(__DIR__.'/../../resources/views/themes');
        $testThemeDir = $themesDir.'/test_theme_fixture';

        mkdir($testThemeDir, 0755, true);
        file_put_contents($testThemeDir.'/pricing-table.blade.php', '{{-- fixture --}}');

        try {
            $themes = BaseSubscriptionComponent::availableThemes();
            $this->assertArrayHasKey('test_theme_fixture', $themes);
            $this->assertSame('Test Theme Fixture', $themes['test_theme_fixture']);
        } finally {
            unlink($testThemeDir.'/pricing-table.blade.php');
            rmdir($testThemeDir);
        }
    }

    // -------------------------------------------------------------------------
    // Published vendor path overrides package themes
    // -------------------------------------------------------------------------

    public function test_published_theme_overrides_package_theme(): void
    {
        $publishedDir = resource_path('views/vendor/subkit/themes/default');

        mkdir($publishedDir, 0755, true);
        file_put_contents($publishedDir.'/pricing-table.blade.php', '{{-- published override --}}');

        try {
            $themes = BaseSubscriptionComponent::availableThemes();
            // Key still 'default', but it must appear (not duplicated)
            $this->assertArrayHasKey('default', $themes);
            $this->assertCount(count(array_unique(array_keys($themes))), $themes, 'No duplicate keys');
        } finally {
            unlink($publishedDir.'/pricing-table.blade.php');
            rmdir($publishedDir);
            // Clean up parent dirs only if empty
            @rmdir(resource_path('views/vendor/subkit/themes'));
            @rmdir(resource_path('views/vendor/subkit'));
            @rmdir(resource_path('views/vendor'));
        }
    }

    public function test_published_path_not_found_does_not_throw(): void
    {
        // vendor path does not exist in test env — must not throw
        $themes = BaseSubscriptionComponent::availableThemes();

        $this->assertIsArray($themes);
    }

    public function test_theme_only_in_published_path_is_discovered(): void
    {
        $publishedDir = resource_path('views/vendor/subkit/themes/brand_new_theme');
        mkdir($publishedDir, 0755, true);
        file_put_contents($publishedDir.'/pricing-table.blade.php', '{{-- fixture --}}');

        try {
            $themes = BaseSubscriptionComponent::availableThemes();
            $this->assertArrayHasKey('brand_new_theme', $themes);
            $this->assertSame('Brand New Theme', $themes['brand_new_theme']);
        } finally {
            unlink($publishedDir.'/pricing-table.blade.php');
            rmdir($publishedDir);
            @rmdir(resource_path('views/vendor/subkit/themes'));
            @rmdir(resource_path('views/vendor/subkit'));
            @rmdir(resource_path('views/vendor'));
        }
    }

    // -------------------------------------------------------------------------
    // render() fallback: effective theme name in view data
    // -------------------------------------------------------------------------

    public function test_unknown_theme_falls_back_to_default_theme_name_in_data(): void
    {
        $component = new class('non_existent_theme') extends BaseSubscriptionComponent
        {
            protected function componentName(): string
            {
                return 'pricing-table';
            }

            protected function getThemeData(): array
            {
                return ['theme' => $this->theme ?? 'default'];
            }
        };

        $view = $component->render();

        // 'theme' in view data must be corrected to 'default', not the non-existent name
        $this->assertSame('default', $view->getData()['theme']);
    }
}
