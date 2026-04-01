<?php

namespace SubKit\Http\Controllers\Api;

use SubKit\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {}

    public function forUser(Request $request): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'string']]);

        return response()->json(
            $this->service->forUser($request->input('user_id'))
        );
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->service->cancel($id, $request->boolean('immediately', false));

        return response()->json(['message' => 'Cancellation queued.']);
    }

    public function resume(int $id): JsonResponse
    {
        $this->service->resume($id);

        return response()->json(['message' => 'Subscription resumed.']);
    }
}
