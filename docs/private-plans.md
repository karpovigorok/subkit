# Private / Custom Plans

Private plans are hidden from public pricing tables and assigned directly to specific users or companies — useful for custom deals, beta access, demo accounts, or enterprise offers.

## Creating a private plan

In the Filament admin panel, open or create a plan and toggle **Private / Custom Plan** on. The plan will be excluded from `<x-subkit::pricing-table>` and from `Plan::public()` queries.

With the toggle on, an **Assigned subscribers** field appears. Search for users by email (or by the field set in `billable_search_column`) and select one or more. Assignments are saved when you save the plan.

> Requires `billable_model` in `config/subkit.php` for the subscriber search to work:
> ```php
> 'billable_model'        => App\Models\User::class,
> 'billable_search_column' => 'email', // default — change to 'name' for team models
> ```

Assignments can also be managed individually on the plan's **Assignments** tab.

## Displaying personal offers to users

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

### All personal-offers props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `theme` | `string\|null` | `'default'` | UI theme (`default`, `dark`, `light`, or custom). |
| `user-id` | `string\|int\|null` | `auth()->id()` | ID of the billable user to load offers for. |
| `company-id` | `string\|int\|null` | `null` | ID of a company/team billable entity. Takes precedence over `user-id`. |
| `provider` | `string` | `'stripe'` | Payment provider. |
| `success-url` | `string` | `''` | Redirect after the user claims the offer. Accepts a route name, relative path, or full URL. |

### $0 / demo plans activate instantly

When the assigned plan has a price of `$0`, clicking **Claim Offer** creates the subscription immediately without opening Stripe Checkout. The user is redirected to `success-url` with a flash message (`session('success')`). No payment method is required.

For paid private plans, the standard Stripe Checkout flow is used.

