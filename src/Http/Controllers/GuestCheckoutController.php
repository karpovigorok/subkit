<?php

namespace SubKit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestCheckoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Cashier syncs the subscription via its webhook handler.
        // Nothing to do here — redirect to the configured success URL.
        return redirect()->to(
            config('subkit.guest_checkout.success_url', '/')
        );
    }
}
