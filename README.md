# SubKit

Laravel subscriptions with a ready-to-use Filament UI.  
Replace days of custom billing logic with a working setup on top of Stripe (Cashier).

- Skip custom subscription logic
- Skip building pricing UI
- Skip wiring Stripe webhooks manually

[![SubKit Pricing](art/demo-how-subkit-works.gif)](art/screen-subkit-theme-default.png)


SubKit provides a Filament admin panel to manage plans and plan sets, themeable Blade components for your pricing page and subscription dashboard, and a clean PHP API for subscription lifecycle operations.

**Why use SubKit?**
While Laravel Cashier is incredibly powerful, building the actual UI and admin panel for subscriptions takes days. SubKit bridges this gap by providing a **"Lickable UI"** out of the box and a powerful Filament admin to manage it all without writing boilerplate code.

## What it does

- Integrates with Stripe via Laravel Cashier (webhooks, checkout sessions, billing portal)
- Provides a Filament admin panel to manage Plans, Plan Sets, Provider Prices, Features, and Limits
- Provides Blade components: a pricing table and a subscription management UI, with multiple themes
- Exposes a PHP facade and REST API for subscription operations (checkout, cancel, resume, billing portal)
- Exposes runtime plan limits (e.g. `max_locations`, `max_users`) via a `HasCapabilities` trait with built-in caching

## Live Demo

Try it in action: **[subkit.noxls.net](https://subkit.noxls.net)**

* Register a test account to explore the customer billing flow.
* Once registered, navigate to **`/admin`** to check out the Filament control panel.

## What it does NOT do

- Process payments or store card data
- Replace Stripe or Laravel Cashier — it orchestrates on top of them
- Handle invoicing, taxes, or compliance
- Manage user authentication or access control

---

## Requirements

- PHP 8.4+
- Laravel 11+
- Laravel Cashier (`laravel/cashier` ^16.5) installed and configured
- Filament (`filament/filament` ^5.0)
- MySQL 8+ (or MariaDB 10.5+)
- A Stripe account

---

## Installation

```bash
composer require karpovigorok/subkit
```

Publish the config and run migrations:

```bash
php artisan vendor:publish --tag=subkit-config
php artisan vendor:publish --tag=subkit-migrations
php artisan migrate
```

Register the plugin in your Filament panel provider:

```php
use SubKit\Filament\SubKitPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SubKitPlugin::make());
}
```

---

## Configuration

```bash
php artisan vendor:publish --tag=subkit-config
```

Add to your `.env`:

```env
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Optional
EASY_SUB_CURRENCY_CODE=USD
EASY_SUB_CURRENCY_SYMBOL=$
```

Your `User` model must use Cashier's `Billable` trait:

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

To enable runtime plan limits and automatic cache flushing on subscription changes, also set `billable_model` in `config/subkit.php`:

```php
'billable_model' => App\Models\User::class,
```

For team-level subscriptions:

```php
'billable_model' => App\Models\Team::class,
```

---

## Stripe setup

### 1. Create plans in the admin panel

Navigate to your Filament admin panel (usually `/admin`) → **Plans** → Create a plan. After creating a plan:
- Add a Stripe Price ID in the **Provider Prices** tab.
- Attach marketing features in the **Features** tab (shown in pricing tables).
- Define technical limits in the **Limits** tab (readable at runtime in your app logic).

### 2. Register the Stripe webhook

Point your Stripe webhook to Cashier's built-in route:

```
https://your-app.com/stripe/webhook
```

Events to enable in Stripe:
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.paid`
- `invoice.payment_failed`

---

## Usage

### Pricing table

Drop the pricing table into any Blade view. The component uses the currently authenticated user automatically — no user ID needed.

```blade
<x-subkit::pricing-table
    provider="stripe"
    success-url="{{ route('dashboard') }}"
    cancel-url="{{ route('pricing') }}"
/>
```

With a plan set (for multiple landing pages or A/B testing):

```blade
<x-subkit::pricing-table
    set="homepage_2024"
    provider="stripe"
    success-url="{{ route('dashboard') }}"
    cancel-url="{{ route('pricing') }}"
/>
```

#### All pricing table props

| Prop | Type | Default | Description                                                                           |
|------|------|---------|---------------------------------------------------------------------------------------|
| `set` | `string\|null` | `null` | Plan set code. If omitted, shows all active plans.                                    |
| `theme` | `string\|null` | `'default'` | UI theme (`default`, `dark`, `light`, or a custom theme).                             |
| `provider` | `string` | `'stripe'` | Payment provider.                                                                     |
| `success-url` | `string` | `''` | Redirect after successful checkout. Accepts a route name, relative path, or full URL. |
| `cancel-url` | `string` | `''` | Redirect when the user cancels checkout.                                              |
| `free-url` | `string` | `''` | CTA destination for $0 plans (authenticated users).                                   |
| `guest-redirect-url` | `string\|null` | `null` | Where unauthenticated visitors are sent. Defaults to `/register`.                     |
| `company-id` | `string\|null` | `null` | For B2B: attaches the subscription to a company rather than a user.                   |
| `subscribe-label` | `string\|null` | `null` | Override the "Get Started" button text.                                               |
| `free-label` | `string\|null` | `null` | Override the "Get Started Free" button text.                                          |
| `guest-label` | `string\|null` | `null` | Override the "Create Account to Subscribe" button text.                               |

URL props accept a **route name** (e.g. `'dashboard'`), a **relative path** (e.g. `'/thanks?utm_source=fb'`), or a **full URL**. Route names are resolved automatically. URLs set in the admin panel (per Plan Set) serve as defaults when the prop is omitted.

For the best UX, point **Free Plan URL** to a route that automatically creates a $0 subscription for the authenticated user.

Button labels follow a three-tier fallback: **Blade prop → Plan Set admin setting → translation string**.

### Manage subscriptions

Drop the manage component into your dashboard or account page:

```blade
<x-subkit::manage-subscriptions
    return-url="{{ route('dashboard') }}"
/>
```

This renders the user's active subscriptions: plan name, status badge, trial/renewal dates, and action buttons (Cancel, Resume, Manage Billing). Renders nothing if the user has no subscriptions.

#### All manage-subscriptions props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `theme` | `string\|null` | `'default'` | UI theme. |
| `return-url` | `string` | `''` | URL to return to from the Stripe billing portal. |
| `guest-redirect-url` | `string\|null` | `null` | Where guests are sent. Renders a redirect link instead of the subscription UI. |

### Check subscription access

```php
use SubKit\Facades\SubKit;

if (SubKit::hasAccess((string) auth()->id())) {
    // User has an active or trialing subscription
}
```

`hasAccess()` returns `true` for `active` and `trialing` states.

### Get subscriptions for a user

```php
$subscriptions = SubKit::forUser((string) auth()->id());

$active = SubKit::activeForUser((string) auth()->id()); // returns Cashier Subscription or null
```

### Cancel a subscription

```php
// Cancel at period end (access continues until the billing period ends)
SubKit::cancel($subscriptionId);

// Cancel immediately
SubKit::cancel($subscriptionId, immediately: true);
```

### Resume a subscription

```php
SubKit::resume($subscriptionId);
```

### Billing portal

Redirect the user to the Stripe-hosted billing portal to manage payment methods and invoices:

```php
$url = SubKit::billingPortal($subscriptionId, route('dashboard'));
return redirect()->away($url);
```

---

## Plan Sets

Plan Sets let you curate groups of plans for specific contexts — landing pages, A/B tests, regional pricing, etc.

Create a plan set in the admin panel under **Plan Sets**, assign plans to it, and reference it by code:

```blade
<x-subkit::pricing-table set="startup_annual" />
```

Per Plan Set you can configure:
- **Theme** — override the default UI theme
- **Description** — subtitle shown above the pricing table
- **URLs** — default success, cancel, free, and guest redirect URLs
- **Button Labels** — override button text per set

---

## Plan Features

SubKit includes a normalized, many-to-many feature management system for **marketing and UI display**.
Instead of hardcoding features in your Blade files, manage a global feature library (e.g., "Priority Support", "Unlimited Projects") in the Filament admin panel under **Features**.

Attach features to plans via the **Features** tab on the plan edit page. SubKit's pricing tables automatically render them with checkmarks inside the pricing cards.

> **Note:** Features are for presentation only — they have no effect on application logic. Use [Plan Limits](#plan-limits) for enforcing technical constraints in your code.

---

## Plan Limits

Plan Limits are **backend-only key-value constraints** per plan (e.g. `max_locations = 100`, `max_maps = 5`). They are intentionally separate from Features: limits drive engine logic, features drive marketing copy.

### Defining limits

In the Filament admin panel, open a plan and go to the **Limits** tab. Each limit has:

| Field | Description |
|-------|-------------|
| **Key** | Snake-case identifier used in code — e.g. `max_locations` |
| **Value** | The raw value stored as a string |
| **Type** | How the value is cast when read: `Integer`, `Boolean`, or `String` |

### Reading limits on a plan

```php
$plan->getLimit('max_locations');        // 100  (int)
$plan->getLimit('can_export');           // true (bool)
$plan->getLimit('tier');                 // 'gold' (string)
$plan->getLimit('nonexistent', 0);       // 0 — custom default
```

### Reading limits at runtime via `HasCapabilities`

Add the `HasCapabilities` trait to your billable model alongside Cashier's `Billable`:

```php
use Laravel\Cashier\Billable;
use SubKit\Concerns\HasCapabilities;

class User extends Authenticatable
{
    use Billable, HasCapabilities;
}
```

Then call `getCapabilities()` anywhere in your application:

```php
$capabilities = $user->getCapabilities();
// ['limits' => ['max_locations' => 100, 'max_maps' => 5]]

$max = $capabilities['limits']['max_locations'] ?? 0;

if ($user->locations()->count() >= $max) {
    abort(403, 'Location limit reached for your plan.');
}
```

`getCapabilities()` resolves the user's active subscription → matches it to a SubKit plan → returns all limits with their types cast. Results are cached for 5 minutes.

If the user has no active subscription, `getCapabilities()` returns `['limits' => []]` without throwing.

#### Flushing the cache manually

```php
$user->flushCapabilitiesCache();
```

#### Automatic cache flush on subscription change

When `billable_model` is set in `config/subkit.php`, SubKit listens to Stripe's `customer.subscription.created/updated/deleted` webhooks and automatically calls `flushCapabilitiesCache()` on the affected model. No extra setup needed.

---

## Private / Custom Plans

Private plans are hidden from public pricing tables and assigned directly to specific users or companies — useful for custom deals, beta access, demo accounts, or enterprise offers.

### Creating a private plan

In the Filament admin panel, open or create a plan and toggle **Private / Custom Plan** on. The plan will be excluded from `<x-subkit::pricing-table>` and from `Plan::public()` queries.

With the toggle on, an **Assigned subscribers** field appears. Search for users by email (or by the field set in `billable_search_column`) and select one or more. Assignments are saved when you save the plan.

> Requires `billable_model` in `config/subkit.php` for the subscriber search to work:
> ```php
> 'billable_model'        => App\Models\User::class,
> 'billable_search_column' => 'email', // default — change to 'name' for team models
> ```

Assignments can also be managed individually on the plan's **Assignments** tab.

### Displaying personal offers to users

Drop `<x-subkit::personal-offers>` anywhere in your dashboard or account page. It queries private plans assigned to the current user and renders a card for each one they have not yet claimed:

```blade
<x-subkit::personal-offers
    success-url="{{ route('dashboard') }}"
/>
```

The component renders **nothing at all** (no wrapper, no heading) when the user has no pending offers. It is safe to include on any page unconditionally.

For B2B — show offers assigned to a company rather than an individual user:

```blade
<x-subkit::personal-offers
    :company-id="(string) $team->id"
    success-url="{{ route('dashboard') }}"
/>
```

Resolve a specific user explicitly (e.g. in an admin preview or email link):

```blade
<x-subkit::personal-offers
    :user-id="(string) $user->id"
    success-url="{{ route('dashboard') }}"
/>
```

When neither `user-id` nor `company-id` is provided, the component falls back to `auth()->id()` automatically.

#### All personal-offers props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `theme` | `string\|null` | `'default'` | UI theme (`default`, `dark`, `light`, or custom). |
| `user-id` | `string\|int\|null` | `auth()->id()` | ID of the billable user to load offers for. |
| `company-id` | `string\|int\|null` | `null` | ID of a company/team billable entity. Takes precedence over `user-id`. |
| `provider` | `string` | `'stripe'` | Payment provider. |
| `success-url` | `string` | `''` | Redirect after the user claims the offer. Accepts a route name, relative path, or full URL. |

#### $0 / demo plans activate instantly

When the assigned plan has a price of `$0`, clicking **Claim Offer** creates the subscription immediately without opening Stripe Checkout. The user is redirected to `success-url` with a flash message (`session('success')`). No payment method is required.

For paid private plans, the standard Stripe Checkout flow is used.

---

## B2B usage

For company-level subscriptions, pass `company-id`:

```blade
<x-subkit::pricing-table
    :company-id="(string) $company->id"
    provider="stripe"
    success-url="{{ route('dashboard') }}"
    cancel-url="{{ route('pricing') }}"
/>
```

`company-id` is a plain string with no foreign key constraint — it can reference any table in your app (`teams`, `organizations`, `workspaces`, etc.).

---

## Themes

Three themes are bundled: `default`, `dark`, and `light`. Specify the theme per component or per Plan Set.

To create a custom theme, publish the views and add a new folder:

```bash
php artisan vendor:publish --tag=subkit-views
```

Create `resources/views/vendor/subkit/themes/{your-theme}/pricing-table.blade.php`. The theme will appear automatically in the admin panel's theme selector.

---

## REST API

| Method | URL | Description |
|--------|-----|-------------|
| `POST` | `/api/subkit/checkout` | Create a Stripe Checkout session |
| `GET`  | `/api/subkit/subscriptions/user` | List subscriptions for the authenticated user |
| `GET`  | `/api/subkit/subscriptions/company` | List subscriptions for a company |
| `POST` | `/api/subkit/subscriptions/{id}/cancel` | Cancel a subscription |
| `POST` | `/api/subkit/subscriptions/{id}/resume` | Resume a subscription |

Add authentication middleware in `config/subkit.php`:

```php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
    'prefix'     => 'api/subkit',
],
```

---

## Subscription states

Subscription state is owned by Cashier and sourced from Stripe's `stripe_status` field:

| State | Meaning |
|-------|---------|
| `trialing` | In a free trial period |
| `active` | Paid and active |
| `past_due` | Payment failed, awaiting retry |
| `paused` | Paused by the customer |
| `canceled` | Canceled (may still have access until period end) |
| `incomplete` | Checkout started but not completed |

---

## Listening to subscription events

SubKit delegates all webhook processing to Cashier. To react to lifecycle changes, listen to Cashier's `WebhookHandled` event in your `AppServiceProvider`:

```php
use Laravel\Cashier\Events\WebhookHandled;

Event::listen(WebhookHandled::class, function (WebhookHandled $event) {
    if ($event->payload['type'] === 'customer.subscription.deleted') {
        // revoke access, send email, etc.
    }
});
```

---

## Publish tags

| Tag | Publishes |
|-----|-----------|
| `subkit-config` | `config/subkit.php` — includes `billable_model` setting |
| `subkit-migrations` | All package migrations (plans, features, limits, etc.) |
| `subkit-views` | Blade views (for customization) |
| `subkit-lang` | Translation strings |

---

## Running tests

```bash
composer test
```

---

## License

MIT
