# Upgrading

Notes for upgrading between SubKit versions. See [CHANGELOG.md](CHANGELOG.md) for the full list of changes.

## v3.0.0

- **New migrations** (Top-ups, provider-neutral Top-ups). Re-publish and run them:

  ```bash
  php artisan vendor:publish --tag=subkit-migrations
  php artisan migrate
  ```

- **New models have no policy, so they are open in the admin.** If you registered policies for SubKit's models earlier, add one for `TopUp` and `TopUpProviderPrice` (and for `PlanAssignment` if you have not), or add a fail-closed `Gate::before`. Filament allows every action on a model without a policy. See [Access control](docs/access-control.md).

- **Custom payment providers must update their adapter.** `PaymentProviderContract` changed in two places; an adapter that is not updated fails with a fatal error when the class loads. Providers that only use the bundled Stripe adapter need no change.

  1. `createCheckoutSession()` takes a trailing `array $extras = []`:

     ```php
     public function createCheckoutSession(
         Model $user,
         string $priceId,
         string $successUrl,
         string $cancelUrl,
         ?int $trialDays = null,
         array $options = [],
         array $extras = [],   // new
     ): string;
     ```

     `$extras` is the validated output of the [checkout hook](docs/checkout-hook.md) and may contain `metadata`, `transfer_data` and `application_fee_percent`. It is `[]` when no hook is registered. Apply what your provider supports and ignore the rest.

  2. New method `createTopUpCheckoutSession()`, called by `SubKit::topUpCheckout()` for one-off [Top-ups](docs/top-ups.md). Return the redirect URL of a one-time (non-subscription) checkout:

     ```php
     public function createTopUpCheckoutSession(
         Model $user,
         \SubKit\Models\TopUp $topUp,   // provider-neutral: name, display amount, currency
         string $priceId,               // this provider's own reference for the Top-up
         string $successUrl,
         string $cancelUrl,
         array $extras = [],            // metadata, transfer_data, application_fee_amount
     ): string;
     ```

     `$extras['metadata']` already contains SubKit's reserved keys (`subkit_kind`, `topup_code`). Pass it through to the provider so your webhook handler can recognise the purchase. If your provider does not support Top-ups, throw an exception here.

- **Identity is server-side.** API and form endpoints act for `$request->user()`; there is no `user_id` parameter. Add authentication middleware to `subkit.api.middleware` (see [REST API](docs/api.md)). Acting for a company requires `SubKit::authorizeBillableUsing()`, and `SubKit::resolveBillableUsing()` names a default billable such as the current team (see [B2B](docs/b2b.md)).
