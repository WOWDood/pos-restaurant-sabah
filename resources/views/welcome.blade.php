<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900">
        {{-- Top nav --}}
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-indigo-600 rounded flex items-center justify-center text-white">
                        &#128722;
                    </div>
                    <span class="font-semibold text-gray-900">Kedai Soto Banso</span>
                </div>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
                   class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-full hover:bg-indigo-700">
                    Dashboard
                </a>
            </div>
        </header>

        {{-- Hero --}}
        <section class="bg-gradient-to-b from-indigo-50 to-white py-20">
            <div class="max-w-3xl mx-auto px-6 text-center">
                <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-medium px-3 py-1 rounded-full mb-4">
                    Restaurant POS System
                </span>
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 leading-tight">
                    Manage Your Restaurant
                </h1>
                <p class="text-4xl md:text-5xl font-bold text-indigo-600 leading-tight mt-1">
                    With Ease
                </p>
                <p class="mt-6 text-gray-600 max-w-2xl mx-auto">
                    A complete point-of-sale solution with QR code table ordering, real-time order management, and seamless payment processing.
                </p>
                <div class="mt-8">
                    <details class="inline-block text-left">
                        <summary class="cursor-pointer bg-white border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-50 list-none">
                            Learn More &#9662;
                        </summary>
                        <div class="absolute mt-2 bg-white border border-gray-200 rounded-lg shadow-lg p-3 text-sm text-gray-600 w-64">
                            Explore the features below to see how our POS system helps you run your restaurant.
                        </div>
                    </details>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section class="py-16">
            <div class="max-w-6xl mx-auto px-6">
                <h2 class="text-2xl font-bold text-gray-900 text-center">Everything You Need</h2>
                <p class="text-gray-500 text-center mt-2">From taking orders to processing payments, our system handles it all.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
                    <x-card>
                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 mb-4">
                            &#9638;
                        </div>
                        <h3 class="font-semibold text-gray-900">QR Code Ordering</h3>
                        <p class="text-sm text-gray-500 mt-2">Customers scan a QR code at their table to browse the menu and place orders directly from their phone.</p>
                    </x-card>
                    <x-card>
                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 mb-4">
                            &#128179;
                        </div>
                        <h3 class="font-semibold text-gray-900">Point of Sale</h3>
                        <p class="text-sm text-gray-500 mt-2">A fast and intuitive cashier interface for quick order entry, table assignment, and receipt printing.</p>
                    </x-card>
                    <x-card>
                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 mb-4">
                            &#9783;
                        </div>
                        <h3 class="font-semibold text-gray-900">Table Management</h3>
                        <p class="text-sm text-gray-500 mt-2">Visual table layout with real-time status tracking. See which tables are occupied, available, or have pending orders.</p>
                    </x-card>
                </div>
            </div>
        </section>

        @fluxScripts
    </body>
</html>