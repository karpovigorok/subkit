<?php

namespace SubKit\Http\Controllers;

use SubKit\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ManageSubscriptionsController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {}

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $this->service->cancel($id, $request->boolean('immediately', false));

        return redirect()->back();
    }

    public function resume(int $id): RedirectResponse
    {
        $this->service->resume($id);

        return redirect()->back();
    }
}
