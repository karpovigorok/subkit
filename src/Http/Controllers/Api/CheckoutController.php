<?php

namespace SubKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SubKit\Services\SubscriptionService;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'string'],
            'company_id' => ['nullable', 'string'],
            'success_url' => ['required', 'string'],
            'cancel_url' => ['required', 'string'],
            'provider' => ['sometimes', 'string'],
        ]);

        $result = $this->service->checkout(
            planCode: $data['plan_code'],
            userId: $this->service->resolveBillable($request, $data['company_id'] ?? null, SubscriptionService::ABILITY_CHECKOUT),
            successUrl: $data['success_url'],
            cancelUrl: $data['cancel_url'],
            provider: $data['provider'] ?? 'stripe',
        );

        if ($result->directlySubscribed) {
            return response()->json([
                'subscribed' => true,
                'redirect_url' => $result->url,
                'message' => __('subkit::messages.subscribed_instantly'),
            ]);
        }

        return response()->json(['checkout_url' => $result->url]);
    }
}
