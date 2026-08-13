<?php

namespace SubKit\View\Components;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Component;

abstract class BaseSubscriptionComponent extends Component
{
    public function __construct(
        public readonly ?string $theme = null,
    ) {}

    abstract protected function componentName(): string;

    abstract protected function getThemeData(): array;

    public function render()
    {
        $requested = $this->theme ?? 'default';
        $themed = "subkit::themes.{$requested}.{$this->componentName()}";

        if (view()->exists($themed)) {
            return view($themed, $this->getThemeData());
        }

        // Requested theme has no view — fall back to default.
        // Override 'theme' in data so @include paths inside the template stay valid.
        return view(
            "subkit::themes.default.{$this->componentName()}",
            array_merge($this->getThemeData(), ['theme' => 'default'])
        );
    }

    /**
     * Resolve a URL value: if it's a named route, return the full URL.
     * Relative paths (starting with /) are made absolute — Stripe requires full URLs.
     * Returns $fallback for empty input.
     */
    protected function resolveUrl(?string $value, string $fallback = '#'): string
    {
        if (empty($value)) {
            return $fallback;
        }

        // Strip accidental surrounding quotes (e.g. 'dashboard' → dashboard).
        $value = trim($value, "'\"");

        if (empty($value)) {
            return $fallback;
        }

        if (Route::has($value)) {
            return route($value);
        }

        if (str_starts_with($value, '/')) {
            return url($value);
        }

        return $value;
    }

    /**
     * Scan theme directories and return valid themes as ['folder' => 'Human Label'].
     *
     * A theme is valid when its folder contains pricing-table.blade.php.
     * The published vendor path is scanned after the package path, so published
     * themes with the same folder name override the built-in ones.
     */
    public static function availableThemes(): array
    {
        $packagePath = realpath(__DIR__.'/../../../resources/views/themes');
        $publishedPath = resource_path('views/vendor/subkit/themes');

        $themes = [];

        foreach ([$packagePath, $publishedPath] as $basePath) {
            if (! $basePath || ! is_dir($basePath)) {
                continue;
            }

            foreach (new \DirectoryIterator($basePath) as $entry) {
                if (! $entry->isDir() || $entry->isDot()) {
                    continue;
                }

                $name = $entry->getFilename();

                // Contract: folder must contain pricing-table.blade.php
                if (! file_exists($entry->getPathname().'/pricing-table.blade.php')) {
                    continue;
                }

                $themes[$name] = Str::headline($name);
            }
        }

        return $themes;
    }
}
