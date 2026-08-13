# Changelog

All notable changes to `subkit` will be documented in this file.

## v2.1.0 - 2026-08-13

### What's new in v2.1.0

#### Private / Custom Plans

Plans can now be marked as **private** in the Filament admin. Private plans are hidden from the public pricing table and only shown to users you specifically assign them to.

- New `is_private` flag on plans
- `PlanAssignment` polymorphic model + two migrations to link plans to individual users
- Filament **Assignments** relation manager — assign plans to users directly from the Plan detail page

#### `<x-subkit::personal-offers>` component

New Blade component that renders privately assigned plans for the authenticated user — same indigo/violet card design as the pricing table.

  ```blade
  <x-subkit::personal-offers
    :success-url="route('dashboard')"
    claim-label="Activate Offer"
/>

  ```
Supports claim-label, success-url, provider, and theme props.

Free / $0 plan support

Plans with no price and no Stripe price ID are now activated instantly — no Stripe Checkout session created. A local subscription record is written directly to the database with a local_ prefixed ID for reliable lifecycle tracking. Cancel and resume also bypass the Stripe API for these subscriptions.

Breaking change

SubscriptionService::checkout() now returns a CheckoutResult value object instead of a plain URL string. Update any direct calls:

```php
  // before
  $url = SubKit::checkout(...);
  
  // after
  $result = SubKit::checkout(...);
  $url = $result->url;
  $wasInstant = $result->directlySubscribed;

```
**Tests**
115 tests, 173 assertions — 12 new tests covering free plan checkout, local subscription lifecycle, and PersonalOffers filtering.

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
