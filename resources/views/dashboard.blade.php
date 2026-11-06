<x-layouts::app :title="__('Dashboard')">
    <div class="dashboard space-y-6">
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="dashboard-eyebrow">{{ $settings['business_name'] ?? 'Your restaurant' }}</p>
                <h1>Today at a glance</h1>
                <p class="dashboard-muted mt-2">{{ $updatedAt->format('l, j F Y') }} · Malaysia time</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('dashboard') }}" class="dashboard-button dashboard-button-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5M4 17v-5h5m-4.3-3a7.5 7.5 0 0 1 12.4-3L20 9M4 15l2.9 3A7.5 7.5 0 0 0 19.3 15"/></svg>
                    Refresh
                </a>
                <a href="{{ route('pos') }}" wire:navigate class="dashboard-button dashboard-button-primary">Open POS <span aria-hidden="true">→</span></a>
            </div>
        </header>

        <section aria-label="Restaurant summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-card class="dashboard-metric dashboard-metric-sales">
                <p class="dashboard-metric-label">Paid sales today</p>
                <p class="dashboard-value"><span>RM</span> {{ number_format($todaySales, 2) }}</p>
                <p class="dashboard-muted dashboard-small">Completed payments only</p>
                <div class="dashboard-metric-foot">
                    @if ($salesDifference == 0)
                        Same total as yesterday
                    @else
                        <span aria-hidden="true">{{ $salesDifference > 0 ? '↑' : '↓' }}</span>
                        RM{{ number_format(abs($salesDifference), 2) }} {{ $salesDifference > 0 ? 'above' : 'below' }} yesterday
                    @endif
                </div>
            </x-card>
            <x-card class="dashboard-metric">
                <p class="dashboard-metric-label">Paid orders today</p>
                <p class="dashboard-value">{{ number_format($todayOrderCount) }}</p>
                <p class="dashboard-muted dashboard-small">Orders completed today</p>
                <div class="dashboard-metric-foot">RM{{ number_format($averageOrder, 2) }} average per order</div>
            </x-card>
            <x-card class="dashboard-metric">
                <p class="dashboard-metric-label">Open bills</p>
                <p class="dashboard-value"><span>RM</span> {{ number_format($openOrderTotal, 2) }}</p>
                <p class="dashboard-muted dashboard-small">{{ $openOrderCount }} unpaid {{ Str::plural('order', $openOrderCount) }} across all dates</p>
                <a href="#open-orders" class="dashboard-metric-foot dashboard-link">Review open bills <span aria-hidden="true">↓</span></a>
            </x-card>
            <x-card class="dashboard-metric">
                <p class="dashboard-metric-label">Tables available</p>
                <p class="dashboard-value">{{ $availableTables }} <span class="dashboard-muted">/ {{ $totalTables }}</span></p>
                <p class="dashboard-muted dashboard-small">{{ $occupiedTables }} with open orders</p>
                <a href="{{ route('pos') }}" wire:navigate class="dashboard-metric-foot dashboard-link">View tables <span aria-hidden="true">→</span></a>
            </x-card>
        </section>

        <div class="dashboard-panels">
            <x-card class="dashboard-panel">
                <figure aria-labelledby="sales-title">
                    <figcaption class="dashboard-section-head">
                        <div><h2 id="sales-title">Sales over 7 days</h2><p class="dashboard-muted dashboard-small mt-1">{{ $salesDays->first()['date']->format('j M') }} – {{ $updatedAt->format('j M') }} · paid orders</p></div>
                        <div class="text-right"><p class="dashboard-section-total">RM{{ number_format($weekSales, 2) }}</p><p class="dashboard-muted dashboard-small">7-day total</p></div>
                    </figcaption>
                    @if ($weekSales == 0)
                        <p class="dashboard-chart-empty">No paid sales in this period. Completed orders will appear here.</p>
                    @endif
                    @if ($weekSales > 0)
                    <div class="dashboard-chart">
                        <div class="dashboard-chart-scale" aria-hidden="true"><span>RM{{ number_format($chartMaximum, 0) }}</span><span>{{ number_format($chartMaximum / 2, 0) }}</span><span>0</span></div>
                        <ol class="dashboard-chart-bars" aria-label="Daily paid sales in Malaysian ringgit">
                            @foreach ($salesDays as $day)
                                <li class="dashboard-chart-day">
                                    <span class="sr-only">{{ $day['date']->format('j F Y') }}: RM{{ number_format($day['sales'], 2) }}, {{ $day['orders'] }} paid {{ Str::plural('order', $day['orders']) }}.</span>
                                    <div class="dashboard-bar-track" aria-hidden="true"><div @class(['dashboard-bar', 'dashboard-bar-today' => $loop->last]) style="height: {{ $day['sales'] / $chartMaximum * 100 }}%"></div></div>
                                    <span class="dashboard-chart-label" aria-hidden="true"><span class="dashboard-day-long">{{ $loop->last ? 'Today' : $day['date']->format('D') }}</span><span class="dashboard-day-compact">{{ $day['date']->format('j') }}</span></span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                    @endif
                </figure>
                <details class="dashboard-sales-details">
                    <summary>Daily sales breakdown</summary>
                    <table class="w-full text-left">
                        <caption class="sr-only">Exact paid sales and order counts for the last seven days</caption>
                        <thead><tr><th scope="col">Date</th><th scope="col" class="text-right">Orders</th><th scope="col" class="text-right">Sales</th></tr></thead>
                        <tbody>
                            @foreach ($salesDays as $day)
                                <tr><th scope="row">{{ $day['date']->format('D, j M') }}</th><td class="text-right">{{ $day['orders'] }}</td><td class="text-right tabular-nums">RM{{ number_format($day['sales'], 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
                <p class="dashboard-muted dashboard-small mt-3">Today is still in progress. Yesterday finished at RM{{ number_format($yesterdaySales, 2) }}.</p>
            </x-card>

            <x-card class="dashboard-panel" id="open-orders">
                <div class="dashboard-section-head"><div><h2>On the floor</h2><p class="dashboard-muted dashboard-small mt-1">Open bills · oldest first</p></div><span class="dashboard-count">{{ $openOrderCount }} open</span></div>
                <div class="dashboard-floor-summary">
                    <p><strong>{{ $availableTables }}</strong> tables available <span class="dashboard-muted">of {{ $totalTables }}</span></p>
                    <div class="dashboard-occupancy" role="meter" aria-label="Tables with open orders" aria-valuemin="0" aria-valuemax="{{ max(1, $totalTables) }}" aria-valuenow="{{ $occupiedTables }}" aria-valuetext="{{ $occupiedTables }} of {{ $totalTables }} tables with open orders"><span style="width: {{ $totalTables > 0 ? $occupiedTables / $totalTables * 100 : 0 }}%"></span></div>
                </div>
                @if ($totalTables === 0)
                    <p class="dashboard-muted dashboard-small mt-3">Add your first table to start taking dine-in orders.</p>
                    <a href="{{ route('tables') }}" wire:navigate class="dashboard-link dashboard-inline-link">Set up tables <span aria-hidden="true">→</span></a>
                @endif
                <ul class="dashboard-open-list">
                    @forelse ($openOrders as $order)
                        <li><a href="{{ route('pos.order', $order) }}" wire:navigate class="dashboard-order-link">
                            <div class="min-w-0"><p class="font-semibold break-words">{{ $order->table?->name ?? 'No table assigned' }}</p><p class="dashboard-muted dashboard-small">#{{ $order->id }} · {{ \App\Models\Order::statusLabel($order->status) }}</p><p class="dashboard-muted dashboard-small">Opened {{ $order->created_at->setTimezone('Asia/Kuala_Lumpur')->diffForHumans($updatedAt) }}</p></div>
                            <span class="dashboard-order-amount">RM{{ number_format($order->total, 2) }} <span aria-hidden="true">›</span></span>
                        </a></li>
                    @empty
                        <li class="dashboard-empty"><span class="dashboard-empty-icon" aria-hidden="true">✓</span><p class="font-semibold">All bills settled</p><p class="dashboard-muted dashboard-small mt-1">New and pre-orders will appear here.</p></li>
                    @endforelse
                </ul>
                @if ($openOrderCount > 5)
                    <p class="dashboard-muted dashboard-small mt-2">Showing 5 of {{ $openOrderCount }} open bills.</p>
                @endif
                <a href="{{ route('pos') }}" wire:navigate class="dashboard-link dashboard-inline-link">{{ $openOrderCount > 0 ? 'Manage tables and orders' : 'Start a new order' }} <span aria-hidden="true">→</span></a>
            </x-card>

            <x-card class="dashboard-panel">
                <div class="dashboard-section-head"><div><h2>Recent orders</h2><p class="dashboard-muted dashboard-small mt-1">Last 6 updated · all statuses</p></div><a href="{{ route('orders') }}" wire:navigate class="dashboard-link dashboard-inline-link">View all <span class="sr-only">orders</span> <span aria-hidden="true">→</span></a></div>
                <ul class="dashboard-recent-list">
                    @forelse ($recentOrders as $order)
                        <li><a href="{{ route('pos.order', $order) }}" wire:navigate class="dashboard-recent-order">
                            <span class="dashboard-recent-id">#{{ $order->id }}<span class="dashboard-muted dashboard-small">{{ $order->updated_at->setTimezone('Asia/Kuala_Lumpur')->format('j M, g:i a') }}</span></span>
                            <span class="dashboard-recent-table">{{ $order->table?->name ?? 'No table assigned' }}<span @class(['dashboard-status', 'dashboard-status-'.$order->status])>{{ \App\Models\Order::statusLabel($order->status) }}</span></span>
                            <span class="dashboard-order-amount">RM{{ number_format($order->total, 2) }} <span aria-hidden="true">›</span></span>
                        </a></li>
                    @empty
                        <li class="dashboard-empty"><p class="font-semibold">Your first order starts here</p><p class="dashboard-muted mt-1">Open POS, choose a table, and add items to begin.</p><a href="{{ route('pos') }}" wire:navigate class="dashboard-link dashboard-inline-link">Open POS <span aria-hidden="true">→</span></a></li>
                    @endforelse
                </ul>
            </x-card>

            <x-card class="dashboard-panel">
                <section aria-labelledby="payments-title">
                    <div class="dashboard-section-head"><div><h2 id="payments-title">Payments today</h2><p class="dashboard-muted dashboard-small mt-1">Received for today's paid orders</p></div></div>
                    <ul class="dashboard-payments">
                        @forelse ($paymentTotals as $payment)
                            <li><div class="flex justify-between gap-3"><span class="break-words min-w-0">{{ $payment->method?->description ?? 'Other payment' }}</span><strong class="shrink-0 tabular-nums">RM{{ number_format($payment->collected, 2) }}</strong></div><div class="dashboard-payment-track" aria-hidden="true"><span style="width: {{ $paymentTotal > 0 ? min(100, $payment->collected / $paymentTotal * 100) : 0 }}%"></span></div></li>
                        @empty
                            <li class="dashboard-muted">Payment methods will appear after your first completed payment today.</li>
                        @endforelse
                    </ul>
                </section>
                <section aria-labelledby="top-items-title" class="dashboard-top-items">
                    <div class="dashboard-section-head"><div><h2 id="top-items-title">Popular today</h2><p class="dashboard-muted dashboard-small mt-1">Top items by quantity · paid orders</p></div></div>
                    <ol>
                        @forelse ($topItems as $item)
                            <li><span class="dashboard-rank" aria-hidden="true">{{ $loop->iteration }}</span><span class="min-w-0 break-words">{{ $item->item_description }}</span><span class="dashboard-item-quantity">{{ $item->sold_quantity }} <span class="dashboard-muted">sold</span></span></li>
                        @empty
                            <li class="dashboard-muted">See what customers love once orders are paid.</li>
                        @endforelse
                    </ol>
                </section>
            </x-card>
        </div>
        <footer class="dashboard-footer dashboard-muted dashboard-small">
            <span>Updated {{ $updatedAt->format('g:i:s a') }} MYT · Refresh for the latest figures.</span>
            <span>Sales use the payment date. Open bills include new and pre-orders.</span>
        </footer>
    </div>
</x-layouts::app>
