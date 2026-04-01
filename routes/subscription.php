<?php

use Illuminate\Support\Facades\Route;
use SubKit\Http\Controllers\Api\CheckoutController;
use SubKit\Http\Controllers\Api\SubscriptionController;
use SubKit\Http\Controllers\BillingPortalController;
use SubKit\Http\Controllers\CheckoutRedirectController;
use SubKit\Http\Controllers\ManageSubscriptionsController;
use SubKit\Http\Controllers\PlanSetPreviewController;

// ─── Web: pricing table + manage-subscriptions component actions ──────────────
Route::middleware(config('subkit.web.middleware', ['web']))
    ->name('subkit.')
    ->group(function () {
        Route::post('subkit/checkout', CheckoutRedirectController::class)
            ->name('checkout.redirect');

        Route::post('subkit/subscriptions/{id}/cancel', [ManageSubscriptionsController::class, 'cancel'])
            ->name('manage.cancel');
        Route::post('subkit/subscriptions/{id}/resume', [ManageSubscriptionsController::class, 'resume'])
            ->name('manage.resume');

        Route::post('subkit/billing-portal', BillingPortalController::class)
            ->name('billing-portal');

        Route::get('subkit/plan-sets/{code}/preview', PlanSetPreviewController::class)
            ->name('plan-set.preview');
    });

// ─── REST API ─────────────────────────────────────────────────────────────────
Route::middleware(config('subkit.api.middleware', ['api']))
    ->prefix(config('subkit.api.prefix', 'api/subkit'))
    ->name('subkit.')
    ->group(function () {
        Route::post('checkout', [CheckoutController::class, 'create'])
            ->name('checkout');

        Route::get('subscriptions/user', [SubscriptionController::class, 'forUser'])
            ->name('subscriptions.user');
        Route::post('subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel'])
            ->name('subscriptions.cancel');
        Route::post('subscriptions/{id}/resume', [SubscriptionController::class, 'resume'])
            ->name('subscriptions.resume');
    });
