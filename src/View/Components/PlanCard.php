<?php

namespace SubKit\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use SubKit\Models\Plan;

class PlanCard extends Component
{
    public function __construct(
        public readonly Plan $plan,
        public readonly ?string $userId = null,
        public readonly ?string $companyId = null,
        public readonly string $successUrl = '',
        public readonly string $cancelUrl = '',
        public readonly string $provider = 'stripe',
        public readonly ?string $price = null,          // display string, e.g. "$9.99"
        public readonly array $features = [],
        public readonly bool $highlighted = false,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerName = null,
    ) {}

    public function render(): View
    {
        return view('subkit::components.plan-card');
    }
}
