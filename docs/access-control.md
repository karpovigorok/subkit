# Access control

SubKit does not manage users, roles or permissions. Identity comes from your app's authentication, and who may do what is your app's decision. SubKit gives you two places to enforce it: the customer-facing endpoints and the Filament admin.

## Customer-facing endpoints

Checkout, top-ups, cancel, resume, the billing portal and the REST API act for the authenticated user (`$request->user()`). There is no `user_id` input. A user can only act on subscriptions they own, and `403` is returned otherwise. Two hooks adapt this to your app, and SubKit never needs to know your roles or your authentication:

- `SubKit::authorizeBillableUsing()` decides whether the user may act for a Team or Company, per ability (`checkout`, `top_up`, `view`, `cancel`, `resume`, `portal`).
- `SubKit::resolveBillableUsing()` names the billable a request acts for (the current team, a tenant, an account behind a custom authentication).

See [B2B](b2b.md) for both. Guests get `401`. See [REST API](api.md) for the middleware the API routes need.

## Filament admin

The SubKit resources are ordinary Filament resources, so they use ordinary [Laravel policies](https://laravel.com/docs/authorization#creating-policies). Register a policy for each model and Filament hides or disables whatever the user may not do.

| Model | Shown in |
|-------|----------|
| `SubKit\Models\Plan` | Plans |
| `SubKit\Models\Feature` | Features |
| `SubKit\Models\PlanSet` | Plan Sets |
| `SubKit\Models\PlanSetItem` | Plan Set → Plans tab |
| `SubKit\Models\PlanProviderPrice` | Plan → Provider prices tab |
| `SubKit\Models\PlanLimit` | Plan → Limits tab |
| `SubKit\Models\PlanAssignment` | Plan → Assignments tab (private plans) |
| `SubKit\Models\TopUp` | Top-ups |
| `SubKit\Models\TopUpProviderPrice` | Top-up → Provider prices tab |
| `Laravel\Cashier\Subscription` | Subscribers |

Relation managers check abilities (`create`, `update`, `delete`, `attach`, `detach`, ...) against the **related** model's policy, not the plan's. A tab is only protected if its own model has a policy.

```php
// AppServiceProvider::boot()
Gate::policy(\SubKit\Models\Plan::class, \App\Policies\PlanPolicy::class);
```

```php
class PlanPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Plan $plan): bool { return true; }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Plan $plan): bool { return $user->isAdmin(); }
    public function delete(User $user, Plan $plan): bool { return $user->isAdmin(); }
    public function deleteAny(User $user): bool { return $user->isAdmin(); }
}
```

### Who can open the panel

That is Filament's `FilamentUser::canAccessPanel()` on your `User` model. Outside the `local` environment Filament refuses users who do not implement it. SubKit does not touch it.

### A model without a policy is open

If no policy exists for a model, Filament allows every action on it. That includes models SubKit adds in a later release. Make the default "deny" so a forgotten model fails closed. One `Gate::before` does it for every SubKit model:

```php
use Illuminate\Support\Facades\Gate;

// AppServiceProvider::boot()
Gate::before(function (User $user, string $ability, array $arguments) {
    $subject = $arguments[0] ?? null;
    $class = is_object($subject) ? $subject::class : $subject;

    if (! is_string($class) || ! str_starts_with($class, 'SubKit\\Models\\')) {
        return null; // not a SubKit model: leave it to your policies
    }

    if ($user->isAdmin()) {
        return null; // let policies (or Filament's default) decide
    }

    // Everyone else: read-only.
    return in_array($ability, ['viewAny', 'view'], true) ? null : false;
});
```

Returning `false` denies the action even when no policy is registered. Returning `null` falls through to your policies. Add `\Laravel\Cashier\Subscription::class` to the check if you want the same rule for Subscribers.

To catch missing policies during development, turn on Filament's strict mode in your panel provider. It throws instead of allowing when a model has no policy:

```php
$panel->strictAuthorization(app()->environment('local', 'testing'));
```

### Hiding records from some admins

Policies decide what a user can **do**, not which rows they **see**. The Subscribers list shows every subscription (with the subscriber's name and email) to anyone who may view it. To show a user only their own rows, add a global scope in your app, for example on `Laravel\Cashier\Subscription` (by `user_id`) and on your `User` model:

```php
\Laravel\Cashier\Subscription::addGlobalScope('own', function (Builder $query) {
    $user = Auth::user();

    if ($user && ! $user->isAdmin()) {
        $query->where('user_id', $user->getKey());
    }
});
```

Things to know:

- A global scope applies to your whole app, not only the admin. Skip it when nobody is logged in so webhooks, queues and the scheduler are unaffected.
- `Auth::user()` uses the default guard. A request authenticated through another guard (for example a Sanctum token) is not scoped unless you pass that guard.
- The **Cancel** and **Resume** actions in Subscribers are custom Filament actions and do not consult policies. The scope above is what limits them to the user's own subscriptions.

### A demo or public site

If visitors can register and open `/admin` (like the SubKit demo), use the three pieces together: read-only policies or the `Gate::before` above, a scope so visitors only see their own records, and an allow-list of owners in your config.

## After upgrading

New releases can add models and Filament resources. Check [Upgrading](upgrading.md) and make sure each new model is covered by a policy or by a fail-closed `Gate::before`.
