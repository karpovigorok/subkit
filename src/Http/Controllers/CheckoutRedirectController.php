<?php

namespace SubKit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SubKit\Services\SubscriptionService;

class CheckoutRedirectController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {}

    /**
     * Create a checkout session and redirect the user to the provider's hosted page.
     * Called by the pricing table form submission.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user(), 401);

        $data = $request->validate([
            'plan_code' => ['required', 'string'],
            'company_id' => ['nullable', 'string'],
            'success_url' => ['required', 'string'],
            'cancel_url' => ['required', 'string'],
            'provider' => ['sometimes', 'string'],
        ]);

        $url = $this->service->checkout(
            planCode: $data['plan_code'],
            userId: (string) $request->user()->id,
            successUrl: $data['success_url'],
            cancelUrl: $data['cancel_url'],
            provider: $data['provider'] ?? 'stripe',
        );

        return redirect()->away($url);
    }
}
