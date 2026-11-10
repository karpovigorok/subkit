<?php

namespace SubKit\View\Components;

use Illuminate\Database\Eloquent\Collection;
use SubKit\Models\TopUp as TopUpModel;

/**
 * <x-subkit::top-up-list /> — every available Top-up as a radio group with one Buy button.
 *
 * One checkout buys one Top-up, so the choice is mutually exclusive (radio buttons, first one preselected).
 * Use :codes="['credits-100', 'credits-500']" (or codes="a,b") to show a subset.
 */
class TopUpList extends BaseSubscriptionComponent
{
    /** @var Collection<int, TopUpModel> */
    public Collection $topUps;

    public string $guestUrl;

    public string $buyLabel;

    /**
     * @param  array<int, string>|string|null  $codes
     */
    public function __construct(
        ?string $theme = null,
        public readonly ?string $companyId = null,
        public string $successUrl = '',
        public string $cancelUrl = '',
        public readonly string $provider = 'stripe',
        public readonly ?string $guestRedirectUrl = null,
        public readonly ?string $label = null,
        array|string|null $codes = null,
    ) {
        parent::__construct($theme ?? 'default');

        $codes = is_string($codes) ? array_filter(array_map('trim', explode(',', $codes))) : $codes;

        $this->topUps = TopUpModel::available($provider)
            ->when($codes, fn ($query) => $query->whereIn('code', $codes))
            ->orderBy('id')
            ->get();

        $this->successUrl = $this->resolveUrl($successUrl);
        $this->cancelUrl = $this->resolveUrl($cancelUrl);
        $this->guestUrl = $this->resolveUrl($guestRedirectUrl, '/register');
        $this->buyLabel = $label ?? __('subkit::messages.top_up.buy');
    }

    protected function componentName(): string
    {
        return 'top-up-list';
    }

    protected function getThemeData(): array
    {
        return [
            'theme' => $this->theme,
            'topUps' => $this->topUps,
            'companyId' => $this->companyId,
            'successUrl' => $this->successUrl,
            'cancelUrl' => $this->cancelUrl,
            'provider' => $this->provider,
            'guestUrl' => $this->guestUrl,
            'buyLabel' => $this->buyLabel,
        ];
    }
}
