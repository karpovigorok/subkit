<?php

namespace SubKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SubKit\Services\SubscriptionService;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {}

    public function forUser(Request $request): JsonResponse
    {
        $request->validate(['company_id' => ['nullable', 'string']]);

        return response()->json(
            $this->service->forUser(
                $this->service->resolveBillable($request, $request->input('company_id'), SubscriptionService::ABILITY_VIEW)
            )
        );
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->service->findAuthorizedSubscription($request, $id, SubscriptionService::ABILITY_CANCEL);

        $this->service->cancel($id, $request->boolean('immediately', false));

        return response()->json(['message' => 'Cancellation queued.']);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $this->service->findAuthorizedSubscription($request, $id, SubscriptionService::ABILITY_RESUME);

        $this->service->resume($id);

        return response()->json(['message' => 'Subscription resumed.']);
    }
}
