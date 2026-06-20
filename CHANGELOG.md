# Changelog

All notable changes to `subkit` will be documented in this file.

## [2.0.0] - 2026-06-20

### Added

- **Plan Limits** — define technical constraints per plan (`max_locations`, `max_users`, etc.) in the Filament admin. Each limit has a key, value, and type (`int`, `bool`, `string`) with automatic casting at read time.
- **`HasCapabilities` trait** — add to your billable `User` or `Team` model to read active plan limits at runtime via `getCapabilities()`. Results are cached for 5 minutes and safely return `['limits' => []]` when no active subscription exists.
- **`Plan::getLimit(string $key, mixed $default = null)`** — convenience method for reading a single typed limit directly from a plan instance.
- **Auto cache invalidation** — limits cache is flushed automatically on `customer.subscription.created/updated/deleted` Stripe webhooks when `billable_model` is set in config.
- **`billable_model` config key** — opt-in setting that activates automatic cache flushing and points SubKit to the host app's billable Eloquent model.

### Changed

- **Filament 3 → Filament 5** — all Filament resources and relation managers upgraded to Filament 5 conventions (`Schema` instead of `Form`, `Filament\Actions` namespace, `recordActions` instead of `actions`).
- **Laravel 12 → Laravel 12 + 13** — `illuminate/contracts` constraint updated to `^12.0||^13.0`; package now supports both versions. These are the primary breaking changes that warrant the major version bump.

## [1.0.0] - 2026-03-27

- Initial release. Stripe integration via Laravel Cashier, Filament admin panel, themed Blade components (pricing table, manage subscriptions), REST API, plan features, plan sets.
