# Changelog

All notable changes to `subkit` will be documented in this file.

## [2.1.0] - 2026-08-13

### Added

- **Private / Custom Plans** — plans can be marked `is_private` in the Filament admin. Private plans are hidden from the public pricing table and only visible to specifically assigned users.
- **`PlanAssignment` model + migrations** — two new migrations (`add_is_private_to_plans_table`, `create_subkit_plan_assignments_table`) link private plans to individual billable models via a polymorphic `assignments` relation.
- **Filament Assignments relation manager** — manage per-user plan assignments directly from the Plan detail page in the admin panel.
- **`<x-subkit::personal-offers>` component** — new Blade component that shows privately assigned plans for the authenticated user. Renders cards in the same indigo/violet design language as the pricing table. Supports `claim-label`, `success-url`, `provider`, and `theme` props.
- **Free / $0 plan support** — plans with `price = null` or `price = 0` and no Stripe price ID are activated instantly without creating a Stripe Checkout session. A local subscription record is created with a `local_` prefixed `stripe_id` and `local:{plan_code}` stored in `stripe_price` for reliable lifecycle tracking.
- **Local subscription cancel/resume** — `SubscriptionService::cancel()` and `resume()` detect local subscriptions by the `local_` prefix and update the database directly, bypassing the Stripe API entirely.
- **`CheckoutResult` value object** — `checkout()` now returns a typed `CheckoutResult(url, directlySubscribed)` instead of a plain string, so callers can distinguish an instant activation from a Stripe Checkout redirect.
- **`billable_model` config key used consistently** — `SubscriptionService` now resolves the billable model from `subkit.billable_model` config rather than `auth.providers.users.model`, matching the rest of the package.
- **Tests** — 12 new tests covering free plan checkout, local subscription lifecycle (cancel/resume), and PersonalOffers filtering logic. Total: 115 tests, 173 assertions.

### Changed

- `PricingTable` filters out private plans from both the global plan list and per-set plan lists, so private plans never appear in public pricing tables.
- `PersonalOffers` filtering uses three conditions to exclude already-claimed plans: active Stripe price match, `local:{plan_code}` match, and legacy `null` stripe_price match (for subscriptions created before the `local:{code}` tracking was introduced).
- Resume button in `manage-subscriptions` template gains an inline `style` gradient fallback to survive Tailwind CSS purging in host apps.

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
