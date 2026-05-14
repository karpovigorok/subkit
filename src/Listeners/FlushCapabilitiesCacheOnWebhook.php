<?php

namespace SubKit\Listeners;

use Laravel\Cashier\Events\WebhookHandled;

class FlushCapabilitiesCacheOnWebhook
{
    private const SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function handle(WebhookHandled $event): void
    {
        if (! in_array($event->payload['type'], self::SUBSCRIPTION_EVENTS)) {
            return;
        }

        $billableModel = config('subkit.billable_model');

        if (! $billableModel) {
            return;
        }

        $customerId = $event->payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return;
        }

        $model = (new $billableModel)->where('stripe_id', $customerId)->first();

        if (! $model || ! method_exists($model, 'flushCapabilitiesCache')) {
            return;
        }

        $model->flushCapabilitiesCache();
    }
}
