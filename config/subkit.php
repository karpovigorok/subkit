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

    /*
    |--------------------------------------------------------------------------
    | Billable Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class that uses Cashier's Billable trait and
    | SubKit's HasCapabilities trait. When set, the package automatically
    | flushes the capabilities cache when a Stripe subscription webhook
    | is received (customer.subscription.created/updated/deleted).
    |
    | Example: App\Models\User::class
    |          App\Models\Team::class
    |
    */

    'billable_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Billable Search Column
    |--------------------------------------------------------------------------
    |
    | The column used to search for subscribers when assigning private plans
    | in the Filament admin panel. Defaults to 'email' (suitable for User
    | models). Change to 'name' or another identifier if your billable model
    | is a Team, Company, or similar that lacks an email column.
    |
    | Example: 'name'  (for team/company models)
    |          'email' (default, for user models)
    |
    */

    'billable_search_column' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Assignable Models
    |--------------------------------------------------------------------------
    |
    | Models that can be assigned a private plan in the Filament admin panel.
    | Keys are human-readable labels shown in the dropdown, values are the
    | fully-qualified class names.
    |
    | Example:
    |   'assignable_models' => [
    |       'User'    => App\Models\User::class,
    |       'Company' => App\Models\Company::class,
    |   ],
    |
    */

    'assignable_models' => [],

];
