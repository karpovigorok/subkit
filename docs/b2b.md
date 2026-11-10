# B2B usage

For company-level subscriptions, pass `company-id`:

```blade
<x-subkit::pricing-table
    :company-id="(string) $company->id"
    provider="stripe"
    success-url="{{ route('dashboard') }}"
    cancel-url="{{ route('pricing') }}"
/>
```

`company-id` is a plain string with no foreign key constraint — it can reference any table in your app (`teams`, `organizations`, `workspaces`, etc.). Set `billable_model` in `config/subkit.php` to the model it refers to.

`company-id` only **names** the billable. It is a form field, so SubKit never trusts it: by default a user can only act for their own billable, and anything else returns `403`. To let users act for a company, register a rule that decides, using your own data, who may:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use SubKit\Facades\SubKit;

// AppServiceProvider::boot()
SubKit::authorizeBillableUsing(
    fn (Authenticatable $user, Model $company) => $user->belongsToTeam($company)
);
```

The same rule protects cancel, resume, the billing portal and the API subscription list: a user can only manage subscriptions whose owner they may act for. A missing company and a company the user may not act for both return `403`, so IDs cannot be enumerated.

## Roles: the ability argument

The rule also receives the ability being exercised as a third argument, so one team member can be allowed to look and another to change things. A rule may declare fewer parameters if it does not need them.

| Ability | Action |
|---------|--------|
| `checkout` | Start a subscription checkout |
| `top_up` | Start a Top-up checkout |
| `view` | List subscriptions (`GET api/subkit/subscriptions/user`) |
| `cancel` | Cancel a subscription |
| `resume` | Resume a subscription |
| `portal` | Open the billing portal |

```php
SubKit::authorizeBillableUsing(function (Authenticatable $user, Model $team, string $ability) {
    return match ($ability) {
        'view' => $user->belongsToTeam($team),
        default => $user->hasTeamRole($team, 'owner'),
    };
});
```

Acting for your own billable never asks the rule.

## A default billable: the current team

By default a request acts for `$request->user()`, so naming a team means sending `company_id` every time. If your app already knows the current team (from the session, a subdomain, a header), tell SubKit once and the client can leave `company_id` out:

```php
use Illuminate\Http\Request;

// AppServiceProvider::boot()
SubKit::resolveBillableUsing(fn (Request $request) => $request->user()?->currentTeam);
```

The callback returns the billable model, or `null` when there is none (the request gets `403`, or `401` for a guest). The same billable is used for checkout, Top-ups, the API list, and for deciding which subscriptions the request may cancel, resume or open in the billing portal.

The result is **trusted**: it comes from your server, never from the request. If you also registered `authorizeBillableUsing()`, it is still asked, per ability, whether the user may act for that billable. That is where roles belong. A `company_id` the client sends is still checked as before, unless it names the billable your callback returned.

This also lets you use an authentication SubKit knows nothing about. If your callback maps a credential (an API key header, say) to a billable, the request does not need `$request->user()` at all:

```php
SubKit::resolveBillableUsing(
    fn (Request $request) => Tenant::where('api_key', $request->bearerToken())->first()?->billable
);
```

Your middleware must still reject requests you do not want to serve. SubKit asks only "which billable?", not "is this token valid?".

The Blade components take their billable from props (`user-id`, `company-id`), so pass it there.

