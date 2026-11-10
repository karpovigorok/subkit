# Usage

## Pricing table

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

### All pricing table props

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

## Manage subscriptions

Drop the manage component into your dashboard or account page:

```blade
<x-subkit::manage-subscriptions
    return-url="{{ route('dashboard') }}"
/>
```

This renders the user's active subscriptions: plan name, status badge, trial/renewal dates, and action buttons (Cancel, Resume, Manage Billing). Renders nothing if the user has no subscriptions.

### All manage-subscriptions props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `theme` | `string\|null` | `'default'` | UI theme. |
| `return-url` | `string` | `''` | URL to return to from the Stripe billing portal. |
| `guest-redirect-url` | `string\|null` | `null` | Where guests are sent. Renders a redirect link instead of the subscription UI. |

## Check subscription access

```php
use SubKit\Facades\SubKit;

if (SubKit::hasAccess((string) auth()->id())) {
    // User has an active or trialing subscription
}
```

`hasAccess()` returns `true` for `active` and `trialing` states.

## Get subscriptions for a user

```php
$subscriptions = SubKit::forUser((string) auth()->id());

$active = SubKit::activeForUser((string) auth()->id()); // returns Cashier Subscription or null
```

## Cancel a subscription

```php
// Cancel at period end (access continues until the billing period ends)
SubKit::cancel($subscriptionId);

// Cancel immediately
SubKit::cancel($subscriptionId, immediately: true);
```

## Resume a subscription

```php
SubKit::resume($subscriptionId);
```

## Billing portal

Redirect the user to the Stripe-hosted billing portal to manage payment methods and invoices:

```php
$url = SubKit::billingPortal($subscriptionId, route('dashboard'));
return redirect()->away($url);
```

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

