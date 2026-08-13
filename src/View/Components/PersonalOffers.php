<?php

namespace SubKit\View\Components;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use SubKit\Models\Plan;

class PersonalOffers extends BaseSubscriptionComponent
{
    public readonly Collection $offers;

    public readonly string $resolvedSuccessUrl;

    public function __construct(
        ?string $theme = null,
        public readonly ?string $userId = null,
        public readonly ?string $companyId = null,
        public readonly string $provider = 'stripe',
        public readonly string $successUrl = '',
        public readonly ?string $claimLabel = null,
    ) {
        parent::__construct($theme ?? 'default');

        $billable = $this->resolveBillable();

        $this->offers = $billable
            ? $this->resolveOffers($billable)
            : new Collection;

        $this->resolvedSuccessUrl = $this->resolveUrl($this->successUrl);
    }

    public function render(): mixed
    {
        if ($this->offers->isEmpty()) {
            return '';
        }

        return parent::render();
    }

    protected function componentName(): string
    {
        return 'personal-offers';
    }

    protected function getThemeData(): array
    {
        return [
            'theme'      => $this->theme,
            'offers'     => $this->offers,
            'provider'   => $this->provider,
            'successUrl' => $this->resolvedSuccessUrl,
            'cancelUrl'  => url()->current(),
            'companyId'  => $this->companyId,
            'labels'     => [
                'claim' => $this->claimLabel ?? __('subkit::messages.buttons.claim_offer'),
            ],
        ];
    }

    private function resolveBillable(): ?Model
    {
        $billableClass = config('subkit.billable_model');

        if (! $billableClass) {
            return null;
        }

        $id = $this->companyId ?? $this->userId ?? auth()->id();

        return $id ? $billableClass::find($id) : null;
    }

    private function resolveOffers(Model $billable): Collection
    {
        $billableClass = get_class($billable);

        $plans = Plan::where('is_active', true)
            ->where('is_private', true)
            ->whereHas('assignments', function ($q) use ($billableClass, $billable) {
                $q->where('assignable_type', $billableClass)
                    ->where('assignable_id', $billable->getKey());
            })
            ->with('features', 'providerPrices', 'limits')
            ->get();

        if ($plans->isEmpty()) {
            return $plans;
        }

        $activeStripePrices = $billable->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->pluck('stripe_price')
            ->toArray();

        return $plans->reject(function (Plan $plan) use ($activeStripePrices) {
            $priceId = $plan->providerPrice($this->provider)?->provider_price_id;

            return ($priceId && in_array($priceId, $activeStripePrices))
                || in_array('local:' . $plan->code, $activeStripePrices)
                || (! $priceId && in_array(null, $activeStripePrices, strict: true));
        })->values();
    }
}
