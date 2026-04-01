<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Subscription as CashierSubscription;
use SubKit\Events\SubscriptionActivated;
use SubKit\Events\SubscriptionCanceled;
use SubKit\Events\SubscriptionCancelScheduled;
use SubKit\Events\SubscriptionCreated;
use SubKit\Events\SubscriptionPastDue;
use SubKit\Events\SubscriptionPaused;
use SubKit\Events\SubscriptionResumed;
use SubKit\Events\SubscriptionTrialStarted;
use SubKit\Listeners\WebhookEventDispatcher;
use Tests\TestCase;

class WebhookEventDispatcherTest extends TestCase
{
    private const CUSTOMER_ID = 'cus_test123';

    private const SUBSCRIPTION_ID = 'sub_test456';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function dispatch(string $type, array $object, array $previous = []): void
    {
        $data = [
            'object' => array_merge(
                ['customer' => self::CUSTOMER_ID, 'id' => self::SUBSCRIPTION_ID],
                $object,
            ),
        ];

        if ($previous) {
            $data['previous_attributes'] = $previous;
        }

        (new WebhookEventDispatcher)->handle(
            new WebhookHandled(['type' => $type, 'data' => $data])
        );
    }

    private function createUser(): User
    {
        return User::factory()->create(['stripe_id' => self::CUSTOMER_ID]);
    }

    private function createSubscription(User $user, string $status = 'active'): CashierSubscription
    {
        return CashierSubscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => self::SUBSCRIPTION_ID,
            'stripe_status' => $status,
            'stripe_price' => 'price_test',
        ]);
    }

    // -------------------------------------------------------------------------
    // customer.subscription.created
    // -------------------------------------------------------------------------

    public function test_subscription_created_fires_on_created_event(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch('customer.subscription.created', ['status' => 'active']);

        Event::assertDispatched(SubscriptionCreated::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_trial_started_also_fires_when_created_with_trialing_status(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user, 'trialing');

        $this->dispatch('customer.subscription.created', ['status' => 'trialing']);

        Event::assertDispatched(SubscriptionCreated::class);
        Event::assertDispatched(SubscriptionTrialStarted::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_trial_started_does_not_fire_when_status_is_active(): void
    {
        Event::fake();
        $this->createUser();

        $this->dispatch('customer.subscription.created', ['status' => 'active']);

        Event::assertNotDispatched(SubscriptionTrialStarted::class);
    }

    // -------------------------------------------------------------------------
    // customer.subscription.deleted
    // -------------------------------------------------------------------------

    public function test_subscription_canceled_fires_on_deleted_event(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch('customer.subscription.deleted', ['status' => 'canceled']);

        Event::assertDispatched(SubscriptionCanceled::class, fn ($e) => $e->user->id === $user->id);
    }

    // -------------------------------------------------------------------------
    // customer.subscription.updated — status transitions
    // -------------------------------------------------------------------------

    public function test_activated_fires_when_status_transitions_to_active(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch('customer.subscription.updated', ['status' => 'active'], ['status' => 'trialing']);

        Event::assertDispatched(SubscriptionActivated::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_past_due_fires_when_status_transitions_to_past_due(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch('customer.subscription.updated', ['status' => 'past_due'], ['status' => 'active']);

        Event::assertDispatched(SubscriptionPastDue::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_paused_fires_when_status_transitions_to_paused(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch('customer.subscription.updated', ['status' => 'paused'], ['status' => 'active']);

        Event::assertDispatched(SubscriptionPaused::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_no_status_event_fires_when_status_is_unchanged(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch('customer.subscription.updated', ['status' => 'active', 'cancel_at_period_end' => false]);

        Event::assertNotDispatched(SubscriptionActivated::class);
        Event::assertNotDispatched(SubscriptionPastDue::class);
        Event::assertNotDispatched(SubscriptionPaused::class);
    }

    // -------------------------------------------------------------------------
    // customer.subscription.updated — cancel_at_period_end transitions
    // -------------------------------------------------------------------------

    public function test_cancel_scheduled_fires_when_cancel_at_period_end_becomes_true(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch(
            'customer.subscription.updated',
            ['status' => 'active', 'cancel_at_period_end' => true],
            ['cancel_at_period_end' => false],
        );

        Event::assertDispatched(SubscriptionCancelScheduled::class, fn ($e) => $e->user->id === $user->id);
    }

    public function test_resumed_fires_when_cancel_at_period_end_becomes_false(): void
    {
        Event::fake();
        $user = $this->createUser();
        $this->createSubscription($user);

        $this->dispatch(
            'customer.subscription.updated',
            ['status' => 'active', 'cancel_at_period_end' => false],
            ['cancel_at_period_end' => true],
        );

        Event::assertDispatched(SubscriptionResumed::class, fn ($e) => $e->user->id === $user->id);
    }

    // -------------------------------------------------------------------------
    // Event carries correct data
    // -------------------------------------------------------------------------

    public function test_event_carries_correct_user_and_subscription(): void
    {
        Event::fake();
        $user = $this->createUser();
        $subscription = $this->createSubscription($user);

        $this->dispatch('customer.subscription.deleted', ['status' => 'canceled']);

        Event::assertDispatched(SubscriptionCanceled::class, function ($e) use ($user, $subscription) {
            return $e->user->id === $user->id
                && $e->subscription->id === $subscription->id;
        });
    }

    public function test_event_carries_raw_payload(): void
    {
        Event::fake();
        $this->createUser();

        $this->dispatch('customer.subscription.deleted', ['status' => 'canceled']);

        Event::assertDispatched(
            SubscriptionCanceled::class,
            fn ($e) => $e->payload['type'] === 'customer.subscription.deleted'
        );
    }

    public function test_subscription_is_null_when_not_found_in_database(): void
    {
        Event::fake();
        $this->createUser();
        // No CashierSubscription created — subscription should be null

        $this->dispatch('customer.subscription.deleted', ['status' => 'canceled']);

        Event::assertDispatched(SubscriptionCanceled::class, fn ($e) => $e->subscription === null);
    }

    // -------------------------------------------------------------------------
    // Unresolvable / unhandled cases
    // -------------------------------------------------------------------------

    public function test_no_events_fire_when_stripe_customer_not_found(): void
    {
        Event::fake($this->ourEvents());
        // No user in DB with matching stripe_id

        $this->dispatch('customer.subscription.deleted', ['status' => 'canceled']);

        Event::assertNothingDispatched();
    }

    public function test_no_events_fire_for_unhandled_webhook_type(): void
    {
        Event::fake($this->ourEvents());
        $this->createUser();

        $this->dispatch('invoice.payment_succeeded', ['amount_paid' => 1000]);

        Event::assertNothingDispatched();
    }

    private function ourEvents(): array
    {
        return [
            SubscriptionCreated::class,
            SubscriptionTrialStarted::class,
            SubscriptionActivated::class,
            SubscriptionCanceled::class,
            SubscriptionCancelScheduled::class,
            SubscriptionResumed::class,
            SubscriptionPastDue::class,
            SubscriptionPaused::class,
        ];
    }

    public function test_no_events_fire_when_customer_field_is_missing(): void
    {
        Event::fake();

        // Payload without 'customer' key at all
        (new WebhookEventDispatcher)->handle(
            new WebhookHandled([
                'type' => 'customer.subscription.deleted',
                'data' => ['object' => ['id' => self::SUBSCRIPTION_ID, 'status' => 'canceled']],
            ])
        );

        Event::assertNothingDispatched();
    }
}
