<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use SubKit\Events\TopUpPurchased;
use SubKit\Listeners\WebhookEventDispatcher;
use SubKit\Models\TopUp;
use Tests\TestCase;

class TopUpPurchasedEventTest extends TestCase
{
    private function dispatch(string $type, array $session): void
    {
        $session = array_merge([
            'id' => 'cs_123', 'customer' => 'cus_top', 'payment_status' => 'paid',
            'amount_total' => 9900, 'currency' => 'usd',
            'metadata' => ['subkit_kind' => 'top-up', 'topup_code' => 'credits-100', 'customer_id' => 'c_1'],
        ], $session);

        (new WebhookEventDispatcher)->handle(new WebhookReceived(['type' => $type, 'data' => ['object' => $session]]));
    }

    public function test_top_up_is_read_from_webhook_received_not_webhook_handled(): void
    {
        // Cashier has no checkout.session handler, so it never dispatches WebhookHandled for it.
        // And if a future Cashier does, the event must still fire only once.
        Event::fake([TopUpPurchased::class]);
        User::factory()->create(['stripe_id' => 'cus_top']);
        TopUp::create(['code' => 'credits-100', 'name' => '100 credits']);

        $session = [
            'id' => 'cs_123', 'customer' => 'cus_top', 'payment_status' => 'paid',
            'metadata' => ['subkit_kind' => 'top-up', 'topup_code' => 'credits-100'],
        ];
        (new WebhookEventDispatcher)->handle(new WebhookHandled(['type' => 'checkout.session.completed', 'data' => ['object' => $session]]));

        Event::assertNotDispatched(TopUpPurchased::class);
    }

    public function test_paid_top_up_checkout_fires_a_provider_neutral_event(): void
    {
        Event::fake([TopUpPurchased::class]);
        $user = User::factory()->create(['stripe_id' => 'cus_top']);
        $topUp = TopUp::create(['code' => 'credits-100', 'name' => '100 credits']);

        $this->dispatch('checkout.session.completed', []);

        Event::assertDispatched(TopUpPurchased::class, function (TopUpPurchased $e) use ($user, $topUp) {
            return $e->billable->is($user)
                && $e->topUpCode === 'credits-100'
                && $e->topUp?->is($topUp)
                && $e->provider === 'stripe'
                && $e->reference === 'cs_123'
                && $e->amount === 9900
                && $e->currency === 'USD'
                && $e->metadata === ['customer_id' => 'c_1']; // SubKit's reserved keys are stripped
        });
    }

    public function test_async_payment_succeeded_fires_too(): void
    {
        Event::fake([TopUpPurchased::class]);
        User::factory()->create(['stripe_id' => 'cus_top']);

        $this->dispatch('checkout.session.async_payment_succeeded', []);

        Event::assertDispatched(TopUpPurchased::class);
    }

    public function test_unpaid_session_does_not_fire(): void
    {
        Event::fake([TopUpPurchased::class]);
        User::factory()->create(['stripe_id' => 'cus_top']);

        $this->dispatch('checkout.session.completed', ['payment_status' => 'unpaid']);

        Event::assertNotDispatched(TopUpPurchased::class);
    }

    public function test_sessions_that_are_not_top_ups_do_not_fire(): void
    {
        Event::fake([TopUpPurchased::class]);
        User::factory()->create(['stripe_id' => 'cus_top']);

        $this->dispatch('checkout.session.completed', ['metadata' => []]);
        $this->dispatch('checkout.session.completed', ['metadata' => ['subkit_kind' => 'subscription']]);
        $this->dispatch('checkout.session.completed', ['metadata' => ['subkit_kind' => 'top-up']]);

        Event::assertNotDispatched(TopUpPurchased::class);
    }

    public function test_deleted_top_up_still_fires_with_the_code(): void
    {
        Event::fake([TopUpPurchased::class]);
        User::factory()->create(['stripe_id' => 'cus_top']);

        $this->dispatch('checkout.session.completed', []);

        Event::assertDispatched(TopUpPurchased::class, fn (TopUpPurchased $e) => $e->topUp === null && $e->topUpCode === 'credits-100');
    }

    public function test_unknown_customer_fires_nothing(): void
    {
        Event::fake([TopUpPurchased::class]);

        $this->dispatch('checkout.session.completed', ['customer' => 'cus_nobody']);

        Event::assertNotDispatched(TopUpPurchased::class);
    }
}
