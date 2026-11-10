# Stripe setup

## 1. Create plans in the admin panel

Navigate to your Filament admin panel (usually `/admin`) → **Plans** → Create a plan. After creating a plan:
- Add a Stripe Price ID in the **Provider Prices** tab.
- Attach marketing features in the **Features** tab (shown in pricing tables).
- Define technical limits in the **Limits** tab (readable at runtime in your app logic).

## 2. Register the Stripe webhook

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
- `checkout.session.completed` (required for [Top-ups](top-ups.md))
- `checkout.session.async_payment_succeeded` (optional, top-ups paid with delayed methods)
- `charge.refunded` (optional, if a refund should reverse a top-up)

