<?php

use SubKit\Providers\Stripe\StripeProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Display currency for the pricing table. Prices are stored in cents.
    | Example: code=USD, symbol=$  →  999 cents = "$9.99"
    |
    */

    'currency' => [
        'code' => env('EASY_SUB_CURRENCY_CODE', 'USD'),
        'symbol' => env('EASY_SUB_CURRENCY_SYMBOL', '$'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Providers
    |--------------------------------------------------------------------------
    |
    | Map provider names to their adapter classes.
    | Each adapter MUST implement PaymentProviderContract.
    |
    */

    'providers' => [
        'stripe' => StripeProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Middleware (pricing table checkout redirect)
    |--------------------------------------------------------------------------
    */

    'web' => [
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Middleware applied to the package REST API routes.
    | Add 'auth:sanctum' or your own guard to protect these endpoints.
    |
    */

    'api' => [
        'middleware' => ['api'],
        'prefix' => 'api/subkit',
    ],

];
