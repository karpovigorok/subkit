<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $planSet->name }} — Preview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 py-12 px-6">

    <div class="max-w-5xl mx-auto">
        <x-subkit::pricing-table
            :set="$planSet->code"
            successUrl="#"
            cancelUrl="#"
        />
    </div>

    <p class="mt-8 text-center text-xs text-gray-400">
        Preview only — buttons are not functional here.
    </p>

</body>
</html>