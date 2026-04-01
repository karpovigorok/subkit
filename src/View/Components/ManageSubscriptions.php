<?php

namespace SubKit\View\Components;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use SubKit\Models\PlanProviderPrice;
use SubKit\Services\SubscriptionService;

class ManageSubscriptions extends BaseSubscriptionComponent
{
    public readonly bool $isGuest;
    public readonly Collection $subscriptions;
    public readonly array $nextBillingDates;
    public readonly array $plans;

    public function __construct(
        private readonly SubscriptionService $service,
        ?string $theme = null,
        public readonly string $returnUrl = '',
        public readonly ?string $guestRedirectUrl = null,
    ) {
        parent::__construct($theme ?? 'default');

        $user = auth()->user();

        $this->isGuest = $user === null;

        $this->subscriptions = $user !== null
            ? $service->forUser((string) $user->id)
            : new Collection();

        $this->nextBillingDates = $this->resolveNextBillingDates();
        $this->plans = $this->resolvePlans();
    }

    protected function componentName(): string
    {
        return 'manage-subscriptions';
    }

    protected function getThemeData(): array
    {
        return [
            'theme'            => $this->theme,
            'isGuest'          => $this->isGuest,
            'subscriptions'    => $this->subscriptions,
            'nextBillingDates' => $this->nextBillingDates,
            'plans'            => $this->plans,
            'returnUrl'        => $this->returnUrl,
            'guestRedirectUrl' => $this->guestRedirectUrl,
        ];
    }

    private function resolveNextBillingDates(): array
    {
        $dates = [];

        foreach ($this->subscriptions as $subscription) {
            if (! $subscription->active() || $subscription->ends_at) {
                continue;
            }

            try {
                $stripe = $subscription->asStripeSubscription();
                $periodEnd = $stripe->items->data[0]->current_period_end ?? null;
                $dates[$subscription->id] = $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null;
            } catch (\Throwable) {
                // leave this subscription out of the dates map
            }
        }

        return $dates;
    }

    private function resolvePlans(): array
    {
        $plans = [];

        foreach ($this->subscriptions as $subscription) {
            $plans[$subscription->id] = PlanProviderPrice::where('provider_price_id', $subscription->stripe_price)
                ->first()
                ?->plan;
        }

        return $plans;
    }
}