# Changelog

All notable changes to `subkit` will be documented in this file.

## v3.0.1 - 2026-11-10

### Documentation

- The `docs/` folder now ships with the package and is tracked in the repository, so the guides are available on GitHub and in `vendor/karpovigorok/subkit/docs` (for editors and AI agents).
- `UPGRADING.md` moved to [`docs/upgrading.md`](docs/upgrading.md).

## v3.0.0 - 2026-11-10

### Added

- **Checkout hook** (`SubKit::resolveCheckoutUsing()`): server-side way to attach `metadata`, Stripe Connect `transfer_data` and `application_fee_percent` to subscription checkouts. Values are validated against an allowlist and are never read from the request (ADR-002).
- **Top-ups**: one-off checkout for a provider-neutral Top-up (`SubKit::topUpCheckout()`, `POST subkit/top-ups/checkout`, `POST api/subkit/top-ups/checkout`, new `subkit_top_ups` and `subkit_top_up_provider_prices` tables), managed in the Filament admin under Subscriptions → Top-ups. SubKit does not track balances (ADR-003).
- **`<x-subkit::top-up code="...">` and `<x-subkit::top-up-list>`** Blade snippets (themeable: default, light, dark). The list is a radio group with one Buy button.
- **`SubKit\Events\TopUpPurchased`**: provider-neutral event fired when a Top-up is paid, so hosts write one listener for every provider.
- **`SubKit::resolveBillableUsing()`**: names the billable a request acts for when the client sends no `company_id` (the current team, a tenant, an account behind a custom authentication). Applies to checkout, Top-ups, the API subscription list and the cancel, resume and billing portal actions. Without it the authenticated user is the billable.
- **Per-ability rules**: the `SubKit::authorizeBillableUsing()` callback also receives the ability (`checkout`, `top_up`, `view`, `cancel`, `resume`, `portal`), so a host can grant some actions and withhold others (for example team roles).

### Documentation

- New [Access control](docs/access-control.md) guide: policies for every SubKit model, a fail-closed `Gate::before`, and hiding records from non-admin users. Note for upgraders: the new Top-up models have no policy and are open in the admin until you add one.

### Security

- **Identity now comes from the authenticated user, never from request input.** `POST api/subkit/checkout` and `GET api/subkit/subscriptions/user` no longer accept `user_id`; the web checkout's `company_id` is authorized server-side instead of being used as the user ID. Cancel, resume and the billing portal now require authentication and check that the subscription's owner is the user, or a billable the host allows via `SubKit::authorizeBillableUsing()`. Previously any request could cancel, resume or open a billing portal for any subscription ID.

### Breaking

- REST API endpoints require authentication (add e.g. `auth:sanctum` to `subkit.api.middleware`). They act for the authenticated user and no longer accept `user_id`; remove it from your requests. To act for a Team or Company, send the optional `company_id` and register `SubKit::authorizeBillableUsing()`.
- B2B apps using `company-id` must register `SubKit::authorizeBillableUsing()`; without it, `company_id` of another billable returns `403`.
- Pricing-table/manage-subscriptions forms and the cancel/resume/portal routes return `401` for guests.

### Changed

- Webhook-driven events now resolve the billable from `subkit.billable_model` (falling back to the auth user model), so Team/Company billables receive subscription events too.
- `SubscriptionService::checkout()` and `forUser()` also accept a billable model instead of an ID.
- `PaymentProviderContract::createCheckoutSession()` takes a trailing `array $extras = []` and the contract gains `createTopUpCheckoutSession(Model $user, TopUp $topUp, string $priceId, ...)`. Custom providers must implement both.

## v2.1.3 - 2026-10-06

### Fixed

- Assigning a subscriber from a plan's Assignments tab threw a Filament validation error (`getOptionLabelUsing` missing on the single-select).

## v2.1.2 - 2026-08-13

### Fixed

- **`HasCapabilities::getCapabilities()` returns empty limits for local/$0 subscriptions** — `resolveCapabilities()` was reading `stripe_price` exclusively from `subscription_items`, which is empty for local subscriptions (Cashier only populates items for real Stripe subscriptions). Now falls back to the subscription's own `stripe_price` column, and handles the `local:{code}` prefix by looking up the plan by code rather than by provider price ID.

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
