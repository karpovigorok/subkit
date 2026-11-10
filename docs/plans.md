# Plans: sets, features and limits

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

## Plan Features

SubKit includes a normalized, many-to-many feature management system for **marketing and UI display**.
Instead of hardcoding features in your Blade files, manage a global feature library (e.g., "Priority Support", "Unlimited Projects") in the Filament admin panel under **Features**.

Attach features to plans via the **Features** tab on the plan edit page. SubKit's pricing tables automatically render them with checkmarks inside the pricing cards.

> **Note:** Features are for presentation only — they have no effect on application logic. Use [Plan Limits](plans.md#plan-limits) for enforcing technical constraints in your code.

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

