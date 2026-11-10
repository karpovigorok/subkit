# SubKit documentation

SubKit orchestrates subscriptions on top of Laravel Cashier: Plans, Plan Sets, themeable Blade components and a Filament admin. It does not process payments.

## Getting started

1. [Installation and configuration](installation.md): requirements, migrations, Filament plugin, env, publish tags
2. [Stripe setup](stripe.md): plans, Price IDs, webhook events

## Guides

| Page | Covers |
|------|--------|
| [Usage](usage.md) | Pricing table, manage-subscriptions, PHP facade, subscription states |
| [Plans](plans.md) | Plan Sets, Features (marketing), Limits and `HasCapabilities` (runtime) |
| [Private plans](private-plans.md) | Custom plans, assignments, personal offers, $0 demo plans |
| [B2B / team billing](b2b.md) | `company-id`, `billable_model`, `authorizeBillableUsing()` |
| [Checkout hook](checkout-hook.md) | Server-side metadata and Stripe Connect options |
| [Top-ups](top-ups.md) | One-off purchases and the `TopUpPurchased` event |
| [Access control](access-control.md) | Who may use the admin and endpoints: policies, `Gate::before`, hiding records |
| [Themes](themes.md) | Bundled themes and custom themes |

## Reference

| Page | Covers |
|------|--------|
| [REST API](api.md) | Endpoints and required auth middleware |
| [Events](events.md) | Cashier `WebhookHandled` and SubKit domain events |
| [Upgrading](upgrading.md) | Steps to take when moving between SubKit versions |

See also [CHANGELOG](../CHANGELOG.md).
