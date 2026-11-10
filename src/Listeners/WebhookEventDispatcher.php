<?php

namespace SubKit\Listeners;

use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Subscription as CashierSubscription;
use SubKit\Checkout\CheckoutOptions;
use SubKit\Events\SubscriptionActivated;
use SubKit\Events\SubscriptionCanceled;
use SubKit\Events\SubscriptionCancelScheduled;
use SubKit\Events\SubscriptionCreated;
use SubKit\Events\SubscriptionPastDue;
use SubKit\Events\SubscriptionPaused;
use SubKit\Events\SubscriptionResumed;
use SubKit\Events\SubscriptionTrialStarted;
use SubKit\Events\TopUpPurchased;
use SubKit\Models\TopUp;

class WebhookEventDispatcher
{
    /** Stripe events that can carry a paid Top-up (the async one covers delayed payment methods). */
    private const TOP_UP_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    /**
     * Cashier dispatches WebhookHandled only for event types it has its own handler for
     * (subscriptions, invoices, ...). checkout.session.* has none, so Top-ups must be read from
     * WebhookReceived, which Cashier dispatches for every event. Each type is therefore handled
     * on exactly one of the two events, so nothing is dispatched twice.
     */
    public function handle(WebhookHandled|WebhookReceived $event): void
    {
        $payload = $event->payload;
        $isTopUp = in_array($payload['type'] ?? '', self::TOP_UP_EVENTS, true);

        if ($isTopUp !== ($event instanceof WebhookReceived)) {
            return;
        }

        $user = $this->resolveUser($payload);
        if (! $user) {
            return;
        }

        if ($isTopUp) {
            $this->handleTopUp($user, $payload);

            return;
        }

        $subscription = $this->resolveSubscription($user, $payload);

        match ($payload['type'] ?? '') {
            'customer.subscription.created' => $this->handleCreated($user, $subscription, $payload),
            'customer.subscription.updated' => $this->handleUpdated($user, $subscription, $payload),
            'customer.subscription.deleted' => SubscriptionCanceled::dispatch($user, $subscription, $payload),
            default => null,
        };
    }

    /**
     * Turn a paid one-off Top-up checkout into the provider-neutral TopUpPurchased event.
     */
    private function handleTopUp(Model $user, array $payload): void
    {
        $session = $payload['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];
        $code = $metadata['topup_code'] ?? null;

        if (($metadata['subkit_kind'] ?? null) !== 'top-up'
            || $code === null
            || ($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $hostMetadata = array_diff_key($metadata, array_flip(CheckoutOptions::RESERVED_METADATA_KEYS));

        TopUpPurchased::dispatch(
            $user,
            (string) $code,
            TopUp::where('code', $code)->first(),
            'stripe',
            (string) ($session['id'] ?? ''),
            $hostMetadata,
            isset($session['amount_total']) ? (int) $session['amount_total'] : null,
            isset($session['currency']) ? strtoupper((string) $session['currency']) : null,
            $payload,
        );
    }

    private function handleCreated(Model $user, ?CashierSubscription $subscription, array $payload): void
    {
        SubscriptionCreated::dispatch($user, $subscription, $payload);

        if (($payload['data']['object']['status'] ?? '') === 'trialing') {
            SubscriptionTrialStarted::dispatch($user, $subscription, $payload);
        }
    }

    private function handleUpdated(Model $user, ?CashierSubscription $subscription, array $payload): void
    {
        $current = $payload['data']['object'];
        $previous = $payload['data']['previous_attributes'] ?? [];

        // Status transition
        $newStatus = $current['status'] ?? null;
        $prevStatus = $previous['status'] ?? null;

        if ($prevStatus !== null && $prevStatus !== $newStatus) {
            match ($newStatus) {
                'active' => SubscriptionActivated::dispatch($user, $subscription, $payload),
                'past_due' => SubscriptionPastDue::dispatch($user, $subscription, $payload),
                'paused' => SubscriptionPaused::dispatch($user, $subscription, $payload),
                default => null,
            };
        }

        // cancel_at_period_end transition
        if (array_key_exists('cancel_at_period_end', $previous)) {
            $wasScheduled = (bool) $previous['cancel_at_period_end'];
            $isScheduled = (bool) ($current['cancel_at_period_end'] ?? false);

            if (! $wasScheduled && $isScheduled) {
                SubscriptionCancelScheduled::dispatch($user, $subscription, $payload);
            } elseif ($wasScheduled && ! $isScheduled) {
                SubscriptionResumed::dispatch($user, $subscription, $payload);
            }
        }
    }

    private function resolveUser(array $payload): ?Model
    {
        $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
        if (! $stripeCustomerId) {
            return null;
        }

        // Same billable model SubscriptionService uses, so Team/Company billables resolve too.
        $userModel = config('subkit.billable_model')
            ?? config('auth.providers.users.model');

        return $userModel::where('stripe_id', $stripeCustomerId)->first();
    }

    private function resolveSubscription(Model $user, array $payload): ?CashierSubscription
    {
        $stripeSubscriptionId = $payload['data']['object']['id'] ?? null;
        if (! $stripeSubscriptionId) {
            return null;
        }

        return $user->subscriptions()->where('stripe_id', $stripeSubscriptionId)->first();
    }
}
