# Themes

Three themes are bundled: `default`, `dark`, and `light`. Specify the theme per component or per Plan Set.

To create a custom theme, publish the views and add a new folder:

```bash
php artisan vendor:publish --tag=subkit-views
```

Create `resources/views/vendor/subkit/themes/{your-theme}/pricing-table.blade.php`. The theme will appear automatically in the admin panel's theme selector.

