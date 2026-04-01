<?php

namespace SubKit\Http\Controllers;

use SubKit\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BillingPortalController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'integer'],
            'return_url'      => ['required', 'string'],
        ]);

        $url = $this->service->billingPortal(
            subscriptionId: (int) $data['subscription_id'],
            returnUrl:      $data['return_url'],
        );

        return redirect()->away($url);
    }
}
