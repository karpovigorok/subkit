<?php

namespace SubKit\Listeners;

use SubKit\Events\SubscriptionActivated;
use SubKit\Events\SubscriptionCancelScheduled;
use SubKit\Events\SubscriptionCanceled;
use SubKit\Events\SubscriptionCreated;
use SubKit\Events\SubscriptionPastDue;
use SubKit\Events\SubscriptionPaused;
use SubKit\Events\SubscriptionResumed;
use SubKit\Events\SubscriptionTrialStarted;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Subscription as CashierSubscription;

class WebhookEventDispatcher
{
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;

        $user = $this->resolveUser($payload);
        if (! $user) {
            return;
        }

        $subscription = $this->resolveSubscription($user, $payload);

        match ($payload['type'] ?? '') {
            'customer.subscription.created' => $this->handleCreated($user, $subscription, $payload),
            'customer.subscription.updated' => $this->handleUpdated($user, $subscription, $payload),
            'customer.subscription.deleted' => SubscriptionCanceled::dispatch($user, $subscription, $payload),
            default                         => null,
        };
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
        $current  = $payload['data']['object'];
        $previous = $payload['data']['previous_attributes'] ?? [];

        // Status transition
        $newStatus  = $current['status'] ?? null;
        $prevStatus = $previous['status'] ?? null;

        if ($prevStatus !== null && $prevStatus !== $newStatus) {
            match ($newStatus) {
                'active'   => SubscriptionActivated::dispatch($user, $subscription, $payload),
                'past_due' => SubscriptionPastDue::dispatch($user, $subscription, $payload),
                'paused'   => SubscriptionPaused::dispatch($user, $subscription, $payload),
                default    => null,
            };
        }

        // cancel_at_period_end transition
        if (array_key_exists('cancel_at_period_end', $previous)) {
            $wasScheduled = (bool) $previous['cancel_at_period_end'];
            $isScheduled  = (bool) ($current['cancel_at_period_end'] ?? false);

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

        $userModel = config('auth.providers.users.model', \App\Models\User::class);

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
