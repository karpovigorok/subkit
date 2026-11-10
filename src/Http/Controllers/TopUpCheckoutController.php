<?php

namespace SubKit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SubKit\Services\SubscriptionService;

/**
 * Starts a one-off Top-up checkout for the authenticated user (ADR-003).
 *
 * Serves both the web route (redirect) and the API route (JSON). The buyer is the request's billable
 * (the authenticated user unless the host registered SubKit::resolveBillableUsing()); price, quantity, metadata and transfer data are never read from the request.
 */
class TopUpCheckoutController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {}

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'top_up_code' => ['required', 'string'],
            'success_url' => ['required', 'string'],
            'cancel_url' => ['required', 'string'],
            'provider' => ['sometimes', 'string'],
        ]);

        $result = $this->service->topUpCheckout(
            topUpCode: $data['top_up_code'],
            user: $this->service->resolveBillable($request, null, SubscriptionService::ABILITY_TOP_UP),
            successUrl: $data['success_url'],
            cancelUrl: $data['cancel_url'],
            provider: $data['provider'] ?? 'stripe',
        );

        if ($request->expectsJson()) {
            return response()->json(['checkout_url' => $result->url]);
        }

        return redirect()->away($result->url);
    }
}
