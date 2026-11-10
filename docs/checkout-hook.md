# Customizing checkout (metadata, Stripe Connect)

Register a **checkout hook** to attach your own data to the Stripe objects SubKit creates. The hook runs on the server and is the **only** source of these values: SubKit never reads `metadata`, `transfer_data` or fees from forms, query strings or the REST API, so a customer cannot route their own payment to another account.

```php
use SubKit\Checkout\CheckoutContext;
use SubKit\Facades\SubKit;

// AppServiceProvider::boot()
SubKit::resolveCheckoutUsing(function (CheckoutContext $context) {
    $affiliate = $context->user->referredBy; // your own, trusted lookup

    return [
        'metadata' => ['customer_id' => $context->user->id],
        // Stripe Connect: the affiliate receives the invoice minus the platform fee (80% kept, 20% paid out).
        'transfer_data' => ['destination' => $affiliate->stripe_account_id],
        'application_fee_percent' => 80, // the share the platform KEEPS
    ];
});
```

`CheckoutContext` exposes `kind` (`subscription` or `top-up`), `user`, `provider`, `plan` / `topUp`, and the current `request`. If the hook reads anything from the request (e.g. a referral cookie), verify it yourself.

| Key | Applies to | Stripe target |
|-----|------------|---------------|
| `metadata` | subscription, top-up | Subscription metadata (persists on renewals); for top-ups, Checkout Session and PaymentIntent |
| `transfer_data` (`['destination' => 'acct_...']`) | both | Destination charge to a connected account |
| `application_fee_percent` | subscription | Percentage the platform keeps |
| `application_fee_amount` | top-up | Fixed amount (smallest currency unit) the platform keeps |

Anything else, reserved metadata keys (`name`, `type`, `is_on_session_checkout`, `plan_code`, `subkit_kind`, `topup_code`) and values Stripe would reject (limits: 50 keys, 40-char keys, 500-char values; no all-digit keys, no empty values, at most 2 decimals on `application_fee_percent`) throw `InvalidCheckoutOptionsException` before Stripe is called. The hook is not called for $0 plans, which never reach Stripe.

> **Connect responsibility:** with destination charges your platform account is liable for refunds, disputes, fees and transfer reversals. SubKit passes the values through and does not abstract this.

