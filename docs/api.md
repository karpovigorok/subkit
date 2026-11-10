# REST API

| Method | URL | Description |
|--------|-----|-------------|
| `POST` | `/api/subkit/checkout` | Create a Stripe Checkout session for the authenticated user (optional `company_id`, see [B2B usage](b2b.md)) |
| `GET`  | `/api/subkit/subscriptions/user` | List subscriptions for the authenticated user (optional `company_id`) |
| `POST` | `/api/subkit/subscriptions/{id}/cancel` | Cancel a subscription |
| `POST` | `/api/subkit/subscriptions/{id}/resume` | Resume a subscription |

Every endpoint acts for `$request->user()`. There is no `user_id` parameter, and unauthenticated requests get `401`, so you MUST add authentication middleware in `config/subkit.php`:

```php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
    'prefix'     => 'api/subkit',
],
```

