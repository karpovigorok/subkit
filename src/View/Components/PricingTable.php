<?php

namespace SubKit\View\Components;

use SubKit\Models\Plan;
use SubKit\Models\PlanSet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;

class PricingTable extends BaseSubscriptionComponent
{
    /** @var Collection<Plan> */
    public Collection $plans;

    /** @var array<int, bool>  plan_id → is_highlighted */
    public array $highlighted;

    /** @var string[]  Unique interval values present in this set, e.g. ['monthly', 'yearly'] */
    public array $intervals;

    /** Default selected interval: 'monthly' if available, otherwise first present */
    public string $defaultInterval;

    /** @var array<string, Collection<Plan>>  plans grouped by interval value */
    public array $plansByInterval;

    public ?string $setDescription;

    // Resolved URLs (after prop → DB → fallback priority)
    protected string $resolvedSuccessUrl;
    protected string $resolvedCancelUrl;
    protected string $resolvedFreeUrl;
    protected string $resolvedGuestUrl;

    // Resolved button labels (after prop → DB → translation fallback priority)
    protected array $resolvedLabels;

    public function __construct(
        ?string $theme = null,
        public readonly ?string $set = null,
        public readonly ?string $companyId = null,
        public string $successUrl = '',
        public string $cancelUrl = '',
        public string $freeUrl = '',
        public readonly string $provider = 'stripe',
        public readonly ?string $guestRedirectUrl = null,
        public readonly ?string $subscribeLabel = null,
        public readonly ?string $freeLabel = null,
        public readonly ?string $guestLabel = null,
    ) {
        if ($set !== null) {
            $planSet = PlanSet::where('code', $set)
                ->where('is_active', true)
                ->firstOrFail();

            $this->plans          = $planSet->plans->where('is_active', true)->values();
            $this->setDescription = $planSet->description;

            $this->highlighted = $this->plans
                ->mapWithKeys(fn (Plan $p) => [$p->id => (bool) $p->pivot->is_highlighted])
                ->all();

            parent::__construct($theme ?? $planSet->theme ?? 'default');

            $this->resolvedSuccessUrl = $this->resolveUrl($this->successUrl ?: $planSet->success_url);
            $this->resolvedCancelUrl  = $this->resolveUrl($this->cancelUrl  ?: $planSet->cancel_url);
            $this->resolvedFreeUrl    = $this->resolveUrl($this->freeUrl    ?: $planSet->free_url);
            $this->resolvedGuestUrl   = $this->resolveUrl(
                $this->guestRedirectUrl ?: $planSet->guest_url,
                '/register'
            );
            $this->resolvedLabels = $this->resolveLabels($planSet);

            $this->freeUrl    = $this->resolvedFreeUrl;
            $this->successUrl = $this->resolvedSuccessUrl;
            $this->cancelUrl  = $this->resolvedCancelUrl;
        } else {
            $this->plans          = Plan::where('is_active', true)->orderBy('id')->get();
            $this->highlighted    = [];
            $this->setDescription = null;

            parent::__construct($theme ?? 'default');

            $this->resolvedSuccessUrl = $this->resolveUrl($this->successUrl);
            $this->resolvedCancelUrl  = $this->resolveUrl($this->cancelUrl);
            $this->resolvedFreeUrl    = $this->resolveUrl($this->freeUrl);
            $this->resolvedGuestUrl   = $this->resolveUrl($this->guestRedirectUrl, '/register');
            $this->resolvedLabels = $this->resolveLabels(null);

            $this->freeUrl    = $this->resolvedFreeUrl;
            $this->successUrl = $this->resolvedSuccessUrl;
            $this->cancelUrl  = $this->resolvedCancelUrl;
        }

        $this->plans->load('features');

        $this->intervals = $this->plans
            ->pluck('interval')
            ->map(fn ($i) => $i->value)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->defaultInterval = in_array('monthly', $this->intervals)
            ? 'monthly'
            : ($this->intervals[0] ?? 'monthly');

        $this->plansByInterval = $this->plans
            ->groupBy(fn (Plan $p) => $p->interval->value)
            ->all();
    }

    /**
     * Resolve a URL value: if it's a named route, return the full URL.
     * Relative paths (starting with /) are made absolute — Stripe requires full URLs.
     * Returns $fallback for empty input.
     */
    protected function resolveUrl(?string $value, string $fallback = '#'): string
    {
        if (empty($value)) {
            return $fallback;
        }

        // Strip accidental surrounding quotes (e.g. 'dashboard' → dashboard).
        $value = trim($value, "'\"");

        if (empty($value)) {
            return $fallback;
        }

        if (Route::has($value)) {
            return route($value);
        }

        if (str_starts_with($value, '/')) {
            return url($value);
        }

        return $value;
    }

    protected function resolveLabels(?PlanSet $planSet): array
    {
        return [
            'monthly'   => __('subkit::messages.pricing.toggle_monthly'),
            'yearly'    => __('subkit::messages.pricing.toggle_yearly'),
            'subscribe' => $this->subscribeLabel ?? $planSet?->subscribe_label ?? __('subkit::messages.buttons.get_started'),
            'free'      => $this->freeLabel      ?? $planSet?->free_label      ?? __('subkit::messages.buttons.get_started_free'),
            'guest'     => $this->guestLabel     ?? $planSet?->guest_label     ?? __('subkit::messages.buttons.create_account_to_subscribe'),
        ];
    }

    protected function componentName(): string
    {
        return 'pricing-table';
    }

    protected function getThemeData(): array
    {
        return [
            'theme'           => $this->theme,
            'plans'           => $this->plans,
            'plansByInterval' => $this->plansByInterval,
            'intervals'       => $this->intervals,
            'defaultInterval' => $this->defaultInterval,
            'highlighted'     => $this->highlighted,
            'setDescription'  => $this->setDescription,
            'companyId'        => $this->companyId,
            'successUrl'       => $this->resolvedSuccessUrl,
            'cancelUrl'        => $this->resolvedCancelUrl,
            'freeUrl'          => $this->resolvedFreeUrl,
            'guestUrl'         => $this->resolvedGuestUrl,
            'provider'         => $this->provider,
            'labels'           => $this->resolvedLabels,
        ];
    }
}
