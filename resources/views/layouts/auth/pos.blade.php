@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="pos-auth antialiased">
        <main class="pos-auth-shell">
            <section class="pos-auth-brand" aria-label="POS System XE">
                <a href="{{ route('home') }}" class="pos-auth-logo" wire:navigate>
                    <span class="pos-auth-mark" aria-hidden="true"><flux:icon.shopping-bag class="size-6" /></span>
                    <span>POS System XE</span>
                </a>
                <div class="pos-auth-intro">
                    <p>Restaurant workspace</p>
                    <h2>Ready for the<br class="hidden lg:block"> next order.</h2>
                    <p>Your tables, orders and payments,<br class="hidden lg:block"> together in one place.</p>
                </div>
                <span class="pos-auth-staff">Staff access</span>
            </section>
            <section class="pos-auth-content" aria-label="Staff login">
                {{ $slot }}
            </section>
        </main>
        <p class="pos-auth-help">Need an account? Ask your system administrator.</p>
        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
