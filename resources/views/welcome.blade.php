<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => null])
    </head>
    <body class="home-page">
        <a href="#main-content" class="home-skip">Skip to content</a>
        <header class="home-header">
            <nav class="home-container home-navigation" aria-label="Main navigation">
                <a href="{{ route('home') }}" class="home-brand">
                    <x-system-logo />
                </a>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="home-dashboard">{{ auth()->check() ? 'Dashboard' : 'Staff Login' }}</a>
            </nav>
        </header>

        <main id="main-content">
            <section class="home-hero" aria-labelledby="home-title">
                <div class="home-container">
                    <span class="home-badge"><span aria-hidden="true"></span>Restaurant POS System</span>
                    <h1 id="home-title">Manage Your Restaurant<br><span>With Ease</span></h1>
                    <p>A complete point-of-sale solution with QR code table ordering, real-time order management, and seamless payment processing.</p>
                    <div class="home-hero-actions">
                        @guest
                            <a href="{{ route('login') }}" class="home-staff-login">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5m5 5H3m9-8h6a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-6" /></svg>
                                Staff Login
                            </a>
                        @endguest
                        <a href="#features" class="home-learn">Learn More <flux:icon.chevron-down class="size-4" /></a>
                    </div>
                </div>
            </section>

            <section id="features" class="home-features" aria-labelledby="features-title">
                <div class="home-container">
                    <div class="home-section-heading">
                        <h2 id="features-title">Everything You Need</h2>
                        <p>From taking orders to processing payments, our system handles it all.</p>
                    </div>
                    <div class="home-feature-grid">
                        <article class="home-feature-card">
                            <div class="home-feature-title"><span class="home-feature-icon"><flux:icon.qr-code class="size-6" /></span><h3>QR Code Ordering</h3></div>
                            <p>Customers scan a QR code at their table to browse the menu, choose modifiers, and build their order from their phone.</p>
                        </article>
                        <article class="home-feature-card">
                            <div class="home-feature-title"><span class="home-feature-icon"><flux:icon.calculator class="size-6" /></span><h3>Point of Sale</h3></div>
                            <p>A fast and intuitive cashier interface for quick order entry, table assignment, and receipt printing.</p>
                        </article>
                        <article class="home-feature-card">
                            <div class="home-feature-title"><span class="home-feature-icon"><flux:icon.table-cells class="size-6" /></span><h3>Table Management</h3></div>
                            <p>Keep track of your restaurant tables. See which tables are available or have open orders waiting for payment.</p>
                        </article>
                        <article class="home-feature-card">
                            <div class="home-feature-title"><span class="home-feature-icon"><flux:icon.book-open class="size-6" /></span><h3>Menu Management</h3></div>
                            <p>Organize menu items with categories, photos, modifiers, and combos. Keep your menu up to date in one place.</p>
                        </article>
                        <article class="home-feature-card">
                            <div class="home-feature-title"><span class="home-feature-icon"><flux:icon.squares-plus class="size-6" /></span><h3>Modifiers &amp; Combos</h3></div>
                            <p>Flexible modifier groups for customizing items, from toppings and sides to combo meal deals with clear pricing.</p>
                        </article>
                        <article class="home-feature-card">
                            <div class="home-feature-title"><span class="home-feature-icon"><flux:icon.credit-card class="size-6" /></span><h3>Payment Processing</h3></div>
                            <p>Record cash, card, and e-wallet payments. Keep payment references together with your orders and printed receipts.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="home-how" aria-labelledby="how-title">
                <div class="home-container">
                    <div class="home-section-heading">
                        <h2 id="how-title">How It Works</h2>
                        <p>Simple steps for your customers to explore the menu at their table.</p>
                    </div>
                    <ol class="home-steps">
                        <li><span class="home-step-number" aria-hidden="true">1</span><h3>Scan QR Code</h3><p>Each table has a unique QR code. Customers scan it with their phone camera to open the menu.</p></li>
                        <li><span class="home-step-number" aria-hidden="true">2</span><h3>Browse &amp; Order</h3><p>Browse the menu with photos, customize items with modifiers, and add favourites to the cart.</p></li>
                        <li><span class="home-step-number" aria-hidden="true">3</span><h3>Pay &amp; Enjoy</h3><p>Ask our staff to confirm your order, pay at the counter, and enjoy your meal.</p></li>
                    </ol>
                </div>
            </section>
        </main>

        <footer class="home-footer">
            <a href="{{ route('home') }}" class="home-brand"><x-system-logo /></a>
            <p>Powered by Laravel &amp; Livewire</p>
        </footer>
        @fluxScripts
    </body>
</html>
