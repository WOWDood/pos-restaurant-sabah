<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $table ? 'Menu' : 'Table not found' }} | {{ $businessName }}</title>
    @vite(['resources/css/app.css', 'resources/css/customer-menu.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="customer-menu-body">
    @if ($table)
        <livewire:pages::customer-menu :table="$table" :business-name="$businessName" />
    @else
        <main class="customer-not-found">
            <p class="customer-eyebrow">{{ $businessName }}</p>
            <h1>Table not found</h1>
            <p>This table link is unavailable. Please scan the QR code on your table again or ask our staff for help.</p>
        </main>
    @endif
    @livewireScripts
</body>
</html>
