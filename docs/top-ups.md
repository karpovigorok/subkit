# Top-ups (one-off purchases)

A **Top-up** is a one-time purchase (credit pack, consumable) sold through the provider's hosted checkout. SubKit only launches the checkout. **You own the balance**: SubKit stores no credits, ledger or refunds.

A Top-up is provider-neutral: it has a name, a code and a display price, and one provider price per payment provider that can sell it (like Plans).

## 1. Create it

Create a **one-time** price in your provider's dashboard, then add the Top-up under **Subscriptions → Top-ups** in the Filament admin: name, code, description, display price, then add a provider price (provider + price ID) in the *Provider Prices* tab. The code cannot be changed afterwards. Without Filament, create `SubKit\Models\TopUp` and `TopUpProviderPrice` rows directly.

The display price is only what the snippets show. The provider price stays authoritative for the charge, so keep them in sync.

## 2. Drop a snippet anywhere

```blade
{{-- One top-up --}}
<x-subkit::top-up
    code="credits-100"
    success-url="{{ route('billing') }}"
    cancel-url="{{ route('billing') }}"
/>

{{-- All available top-ups: radio buttons, first one preselected, one Buy button --}}
<x-subkit::top-up-list
    success-url="{{ route('billing') }}"
    cancel-url="{{ route('billing') }}"
    :codes="['credits-100', 'credits-500']"
/>
```

| Prop | Applies to | Default | Description |
|------|-----------|---------|-------------|
| `code` | `top-up` | required | Code of the Top-up to sell |
| `codes` | `top-up-list` | all | Limit the list: array or `"a,b"` |
| `theme` | both | `default` | Theme folder. Themes without top-up views fall back to `default` |
| `success-url` / `cancel-url` | both | `#` | Route name, path or URL |
| `provider` | both | `stripe` | Only top-ups with a price for this provider are shown |
| `company-id` | both | `null` | B2B: act for this billable (see [B2B usage](b2b.md)) |
| `guest-redirect-url` | both | `/register` | Where guests are sent |
| `label` | both | "Buy now" | Button label |

Both snippets render nothing when no top-up is available (inactive, unknown code, or no price for the provider), so a page never breaks because an admin deactivated a pack. Guests see the pack and a "Create account to buy" link. The form posts only the top-up *code*: price, quantity and metadata are never taken from the request.

Custom themes: add `top-up.blade.php` and `top-up-list.blade.php` to your theme folder (variables: `$topUp` / `$topUps`, `$provider`, `$successUrl`, `$cancelUrl`, `$companyId`, `$guestUrl`, `$buyLabel`). Or start the checkout from PHP (e.g. for a Team billable): `SubKit::topUpCheckout('credits-100', $team, $successUrl, $cancelUrl)`. The REST API equivalent is `POST api/subkit/top-ups/checkout` and requires auth middleware on `subkit.api.middleware`.

## 3. Grant what was bought

Listen to SubKit's provider-neutral `TopUpPurchased` event. The same listener works for every payment provider:

```php
use SubKit\Events\TopUpPurchased;

Event::listen(TopUpPurchased::class, function (TopUpPurchased $event) {
    // The event can arrive more than once (providers redeliver webhooks):
    // de-duplicate on $event->reference before granting anything.
    if (Purchase::where('reference', $event->reference)->exists()) {
        return;
    }

    // $event->billable   the User/Team that bought it
    // $event->topUpCode  what was bought ("credits-100")
    // $event->metadata   whatever your checkout hook added
    // $event->amount / $event->currency  as reported by the provider
});
```

It fires only for **paid** purchases (including delayed payment methods once they succeed). Handle refunds and disputes yourself (for Stripe: `charge.refunded`, `charge.dispute.*` via Cashier's `WebhookHandled`) if a refund should reverse the credit. Quantity and price are fixed by the Top-up definition.

