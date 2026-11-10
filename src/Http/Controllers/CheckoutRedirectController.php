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
        $data = $request->validate([
            'plan_code' => ['required', 'string'],
            'company_id' => ['nullable', 'string'],
            'success_url' => ['required', 'string'],
            'cancel_url' => ['required', 'string'],
            'provider' => ['sometimes', 'string'],
        ]);

        // company_id only NAMES a billable; whether this user may act for it is decided server-side
        // (the request's billable by default, or the host's SubKit::authorizeBillableUsing() rule).
        $billable = $this->service->resolveBillable($request, $data['company_id'] ?? null, SubscriptionService::ABILITY_CHECKOUT);

        $result = $this->service->checkout(
            planCode: $data['plan_code'],
            userId: $billable,
            successUrl: $data['success_url'],
            cancelUrl: $data['cancel_url'],
            provider: $data['provider'] ?? 'stripe',
        );

        if ($result->directlySubscribed) {
            return redirect($result->url)->with('success', __('subkit::messages.subscribed_instantly'));
        }

        return redirect()->away($result->url);
    }
}
