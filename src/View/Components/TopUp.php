<?php

namespace SubKit\View\Components;

use SubKit\Models\TopUp as TopUpModel;

/**
 * <x-subkit::top-up code="credits-100" /> — a single Top-up with a Buy button.
 *
 * Renders nothing when the Top-up is unknown, inactive, or not sold by the chosen provider,
 * so a marketing page never breaks because an admin deactivated a pack.
 */
class TopUp extends BaseSubscriptionComponent
{
    public ?TopUpModel $topUp;

    public string $guestUrl;

    public string $buyLabel;

    public function __construct(
        public readonly string $code,
        ?string $theme = null,
        public readonly ?string $companyId = null,
        public string $successUrl = '',
        public string $cancelUrl = '',
        public readonly string $provider = 'stripe',
        public readonly ?string $guestRedirectUrl = null,
        public readonly ?string $label = null,
    ) {
        parent::__construct($theme ?? 'default');

        $this->topUp = TopUpModel::available($provider)->where('code', $code)->first();

        $this->successUrl = $this->resolveUrl($successUrl);
        $this->cancelUrl = $this->resolveUrl($cancelUrl);
        $this->guestUrl = $this->resolveUrl($guestRedirectUrl, '/register');
        $this->buyLabel = $label ?? __('subkit::messages.top_up.buy');
    }

    protected function componentName(): string
    {
        return 'top-up';
    }

    protected function getThemeData(): array
    {
        return [
            'theme' => $this->theme,
            'topUp' => $this->topUp,
            'companyId' => $this->companyId,
            'successUrl' => $this->successUrl,
            'cancelUrl' => $this->cancelUrl,
            'provider' => $this->provider,
            'guestUrl' => $this->guestUrl,
            'buyLabel' => $this->buyLabel,
        ];
    }
}
