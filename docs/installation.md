# Installation and configuration

## Installation

```bash
composer require karpovigorok/subkit
```

Publish the config and run migrations:

```bash
php artisan vendor:publish --tag=subkit-config
php artisan vendor:publish --tag=subkit-migrations
php artisan migrate
```

Register the plugin in your Filament panel provider:

```php
use SubKit\Filament\SubKitPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SubKitPlugin::make());
}
```

## Configuration

```bash
php artisan vendor:publish --tag=subkit-config
```

Add to your `.env`:

```env
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Optional
EASY_SUB_CURRENCY_CODE=USD
EASY_SUB_CURRENCY_SYMBOL=$
```

Your `User` model must use Cashier's `Billable` trait:

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

To enable runtime plan limits and automatic cache flushing on subscription changes, also set `billable_model` in `config/subkit.php`:

```php
'billable_model' => App\Models\User::class,
```

For team-level subscriptions:

```php
'billable_model' => App\Models\Team::class,
```

## Publish tags

| Tag | Publishes |
|-----|-----------|
| `subkit-config` | `config/subkit.php` — includes `billable_model` setting |
| `subkit-migrations` | All package migrations (plans, features, limits, etc.) |
| `subkit-views` | Blade views (for customization) |
| `subkit-lang` | Translation strings |

