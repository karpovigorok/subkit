# Listening to subscription events

SubKit delegates all webhook processing to Cashier. To react to lifecycle changes, listen to Cashier's `WebhookHandled` event in your `AppServiceProvider`:

```php
use Laravel\Cashier\Events\WebhookHandled;

Event::listen(WebhookHandled::class, function (WebhookHandled $event) {
    if ($event->payload['type'] === 'customer.subscription.deleted') {
        // revoke access, send email, etc.
    }
});
```


## Top-up purchases

One-off Top-up purchases emit SubKit's own provider-neutral `TopUpPurchased` event. See [Top-ups](top-ups.md#3-grant-what-was-bought).
