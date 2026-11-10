## SubKit

SubKit (`karpovigorok/subkit`) orchestrates subscriptions on top of Laravel Cashier. It does not process payments.

- Cashier owns the Stripe lifecycle. Subscription state is `subscriptions.stripe_status`. Do not reimplement webhooks, state machines or billing logic.
- Identity is always server-side: `$request->user()`, or the billable from `SubKit::resolveBillableUsing()`. Never accept `user_id` from forms or API input. `company-id` only names a billable; access is decided by `SubKit::authorizeBillableUsing()` (callback gets the user, the billable and the ability).
- The billable model must use Cashier's `Billable` trait. Add `SubKit\Concerns\HasCapabilities` to read plan limits at runtime (`$user->getCapabilities()`).
- Features are for marketing display only; use Limits for enforcement.
- Checkout metadata and Stripe Connect options come only from `SubKit::resolveCheckoutUsing()`, never from the request.
- Top-ups are one-off purchases. SubKit stores no balance: grant credits by listening to `SubKit\Events\TopUpPurchased` and de-duplicate on `$event->reference`.
- Blade components: @verbatim`<x-subkit::pricing-table />`, `<x-subkit::manage-subscriptions />`, `<x-subkit::personal-offers />`, `<x-subkit::top-up />`, `<x-subkit::top-up-list />`@endverbatim.
- Full docs: https://github.com/karpovigorok/subkit/tree/main/docs
