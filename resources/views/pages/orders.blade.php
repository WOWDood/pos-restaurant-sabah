<?php

use App\Models\BusinessSetting;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;

new #[Title('Orders')] class extends Component
{
    public function boot(): void
    {
        auth()->user()?->unsetRelation('staffRole');
        Gate::authorize('view-orders');
    }

    use WithPagination;

    public ?string $table = null;

    public string $status = 'all';

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?Order $selectedOrder = null;

    public bool $showDetailModal = false;

    public bool $showReceiptModal = false;

    public function mount(): void
    {
        $this->table = request()->query('table');
        $this->search = $this->table ?? '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->applyFilters();
    }

    public function updatedDateTo(): void
    {
        $this->applyFilters();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
        $this->resetValidation(['dateFrom', 'dateTo']);
        $this->validate($this->dateRules(), [
            'dateFrom.date_format' => 'Enter a valid start date.',
            'dateTo.date_format' => 'Enter a valid end date.',
            'dateTo.after_or_equal' => 'End date must be on or after the start date.',
        ]);
    }

    public function clearFilters(): void
    {
        $this->reset(['table', 'search', 'status', 'dateFrom', 'dateTo']);
        $this->resetValidation();
        $this->resetPage();
    }

    /** @return array<string, array<int, string>> */
    private function dateRules(): array
    {
        return [
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => $this->dateFrom !== ''
                ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:dateFrom']
                : ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return Builder<Order> */
    private function filteredOrders(): Builder
    {
        $query = Order::query()->when($this->status !== 'all', fn (Builder $query): Builder => $query->where('status', $this->status));
        if (Validator::make(['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo], $this->dateRules())->fails()) {
            return $query->whereRaw('1 = 0');
        }

        $search = trim($this->search);
        if ($search !== '') {
            $orderId = filter_var(ltrim($search, '#'), FILTER_VALIDATE_INT);
            $query->where(function (Builder $query) use ($search, $orderId): void {
                $query->whereHas('table', fn (Builder $table): Builder => $table->where('name', 'like', '%'.$search.'%'));
                if ($orderId !== false && $orderId > 0) {
                    $query->orWhere('id', $orderId);
                }
            });
        }
        if ($this->dateFrom !== '') {
            $query->where('created_at', '>=', CarbonImmutable::parse($this->dateFrom, 'Asia/Kuala_Lumpur')->setTimezone(config('app.timezone')));
        }
        if ($this->dateTo !== '') {
            $query->where('created_at', '<', CarbonImmutable::parse($this->dateTo, 'Asia/Kuala_Lumpur')->addDay()->setTimezone(config('app.timezone')));
        }

        return $query;
    }

    public function setStatus(string $status): void
    {
        if ($status !== 'all' && ! in_array($status, Order::STATUSES, true)) {
            return;
        }

        $this->status = $status;
        $this->resetPage();
    }

    public function clearTableFilter(): void
    {
        $this->table = null;
        $this->search = '';
        $this->resetPage();
    }

    public function openOrderDetail(int $orderId): void
    {
        $this->selectedOrder = Order::query()
            ->with(['table', 'items'])
            ->findOrFail($orderId);
        $this->showDetailModal = true;
    }

    public function closeOrderDetail(): void
    {
        $this->showDetailModal = false;
    }

    public function openReceipt(int $orderId): void
    {
        $order = Order::query()
            ->with(['table', 'items', 'payments.method', 'receipt'])
            ->findOrFail($orderId);

        if ($order->status !== Order::STATUS_PAID) {
            return;
        }

        $this->selectedOrder = $order;
        $this->showReceiptModal = true;
    }

    public function closeReceipt(): void
    {
        $this->showReceiptModal = false;
    }

    public function markReceiptPrinted(): void
    {
        if ($this->selectedOrder?->receipt) {
            $this->selectedOrder->receipt->update(['printed_at' => now()]);
        }
    }

    public function getBusinessSettingsProperty()
    {
        return BusinessSetting::settings();
    }

    public function getOrdersProperty()
    {
        return $this->filteredOrders()
            ->with(['table', 'payments.method'])
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function getUnpaidCountProperty(): int
    {
        return $this->filteredOrders()
            ->whereIn('status', Order::OPEN_STATUSES)
            ->count();
    }
}; ?>

<div class="space-y-4 operations-page">
    <h1 class="text-xl font-bold text-gray-900">Orders</h1>

    <x-card>
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @foreach (['all' => 'All', Order::STATUS_NEW => 'New', Order::STATUS_PRE_ORDER => 'Pre-Order', Order::STATUS_PAID => 'Paid', Order::STATUS_CANCELLED => 'Cancelled'] as $value => $label)
                <button
                    wire:click="setStatus('{{ $value }}')"
                    class="px-3 py-1.5 rounded text-sm font-medium {{ $status === $value ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($table)
            <div class="flex items-center justify-between gap-3 bg-indigo-50 border border-indigo-300 text-indigo-700 rounded-lg px-4 py-2 mb-3 text-sm">
                <span class="flex items-center gap-2">
                    <flux:icon.funnel class="w-4 h-4" />
                    Filtered by table: {{ $table }}
                </span>
                <button wire:click="clearTableFilter" class="text-indigo-700 hover:text-indigo-900 font-medium underline">
                    Show all orders
                </button>
            </div>
        @endif

        @if ($this->unpaidCount > 0)
            <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-lg px-4 py-2 mb-3 text-sm">
                <flux:icon.clock class="w-4 h-4" />
                {{ $this->unpaidCount }} unpaid order(s) waiting for payment
            </div>
        @endif

        <form wire:submit="applyFilters" class="order-filters mb-4">
            <label class="order-search">Search orders
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Table or order #" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </label>
            <label>From date
                <input type="date" wire:model.live="dateFrom" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </label>
            <label>To date
                <input type="date" wire:model.live="dateTo" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </label>
            <button type="submit" wire:loading.attr="disabled" wire:target="applyFilters" class="bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded">Search</button>
            <button type="button" wire:click="clearFilters" class="text-sm text-indigo-700 px-3 py-2 rounded border border-gray-300">Clear filters</button>
        </form>
        <div role="alert">@error('dateFrom') <p class="text-sm text-red-700 mb-2">{{ $message }}</p> @enderror @error('dateTo') <p class="text-sm text-red-700 mb-2">{{ $message }}</p> @enderror</div>
        <p class="text-sm text-gray-600 mb-3">Dates filter when orders were created, in Malaysia time.</p>
        <p wire:loading.delay wire:target="search,dateFrom,dateTo,status,applyFilters,clearFilters" role="status" class="text-sm text-indigo-700 mb-3">Updating orders…</p>

        <x-table class="record-table">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Order #</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Table</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Status</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Total</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Payment</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Created</th>
                    <th class="text-right px-3 py-2 font-medium text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr class="border-t border-gray-100">
                        <td data-label="Order #" class="px-3 py-2">#{{ $order->id }}</td>
                        <td data-label="Table" class="px-3 py-2">{{ $order->table->name ?? '-' }}</td>
                        <td data-label="Status" class="px-3 py-2">
                            <span class="inline-block px-2 py-0.5 rounded text-sm font-medium {{ Order::statusBadgeClasses($order->status) }}">
                                {{ Order::statusLabel($order->status) }}
                            </span>
                        </td>
                        <td data-label="Total" class="px-3 py-2">RM{{ number_format((float) $order->total, 2) }}</td>
                        <td data-label="Payment" class="px-3 py-2">{{ $order->payments->sortByDesc('id')->first()?->method?->description ?? '-' }}</td>
                        <td data-label="Created" class="px-3 py-2">{{ $order->created_at->setTimezone('Asia/Kuala_Lumpur')->format('d M Y H:i') }}</td>
                        <td data-label="Actions" class="px-3 py-2 text-right">
                            <div class="row-actions row-actions-end">
                                @can('use-pos')<a href="{{ route('pos.order', $order) }}" class="row-action row-action-primary" wire:navigate>
                                    <flux:icon.list-bullet class="w-3.5 h-3.5" />
                                    Open
                                </a>@endcan
                                <button wire:click="openOrderDetail({{ $order->id }})" class="row-action row-action-neutral">View</button>
                                @if ($order->status === Order::STATUS_PAID)
                                    <button wire:click="openReceipt({{ $order->id }})" class="row-action">Print</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-gray-100">
                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>

        <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>{{ $this->orders->total() }} records | Page {{ $this->orders->currentPage() }} of {{ $this->orders->lastPage() }}</span>
            <div class="flex gap-2">
                @if ($this->orders->onFirstPage())
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Previous</button>
                @else
                    <button wire:click="previousPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</button>
                @endif
                @if ($this->orders->hasMorePages())
                    <button wire:click="nextPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Next</button>
                @else
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Next</button>
                @endif
            </div>
        </div>
    </x-card>

    <x-modal :show="$showDetailModal" title="{{ $selectedOrder ? 'Order #'.$selectedOrder->id : 'Order Detail' }}" close="closeOrderDetail" wide>
        @if ($selectedOrder)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">Table</span><p class="font-medium">{{ $selectedOrder->table->name ?? '-' }}</p></div>
                    <div><span class="text-gray-500">Status</span><p class="font-medium">{{ Order::statusLabel($selectedOrder->status) }}</p></div>
                    <div><span class="text-gray-500">Total</span><p class="font-medium">RM{{ number_format((float) $selectedOrder->total, 2) }}</p></div>
                    <div><span class="text-gray-500">Created</span><p class="font-medium">{{ $selectedOrder->created_at->format('d M Y H:i') }}</p></div>
                </div>
                <x-table>
                    <thead class="bg-gray-50"><tr><th class="text-left px-3 py-2">Item</th><th class="text-left px-3 py-2">Qty</th><th class="text-right px-3 py-2">Price</th><th class="text-right px-3 py-2">Subtotal</th><th class="text-left px-3 py-2">Notes</th></tr></thead>
                    <tbody>
                        @foreach ($selectedOrder->items as $line)
                            <tr class="border-t border-gray-100 align-top">
                                <td class="px-3 py-2">
                                    <p>{{ $line->item_description }}</p>
                                    @if (! empty($line->modifiers))
                                        <p class="text-sm text-gray-500">{{ collect($line->modifiers)->pluck('name_en')->join(', ') }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $line->quantity }}</td>
                                <td class="px-3 py-2 text-right">RM{{ number_format((float) $line->unit_price, 2) }}</td>
                                <td class="px-3 py-2 text-right">RM{{ number_format((float) $line->subtotal, 2) }}</td>
                                <td class="px-3 py-2">{{ $line->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </div>
        @endif
    </x-modal>

    <x-modal :show="$showReceiptModal" title="Receipt" close="closeReceipt" wide class="receipt-modal">
        @if ($showReceiptModal && $selectedOrder)
            @php
                $receipt = $selectedOrder->receipt;
                $payment = $selectedOrder->payments->sortByDesc('id')->first();
                $settings = $this->businessSettings;
            @endphp
            <div id="receipt-print" class="receipt-print space-y-3 text-sm">
                <div class="text-center">
                    <p class="text-lg font-bold">{{ $settings['business_name'] ?? 'Kedai Soto Banso' }}</p>
                    <p class="whitespace-pre-line">{{ $settings['location'] ?? '' }}</p>
                    <p>{{ $settings['phone'] ?? '' }}</p>
                @if ($settings['email'])<p>{{ $settings['email'] }}</p>@endif
                </div>
                <div class="border-t border-b border-gray-300 py-2 space-y-1">
                    <div class="flex justify-between"><span>Receipt</span><span>{{ $receipt?->receipt_number ?? '-' }}</span></div>
                    <div class="flex justify-between"><span>Order #</span><span>{{ $selectedOrder->id }}</span></div>
                    <div class="flex justify-between"><span>Table</span><span>{{ $selectedOrder->table->name ?? '-' }}</span></div>
                    <div class="flex justify-between"><span>Date</span><span>{{ $selectedOrder->paid_at?->format('d M Y') }}</span></div>
                    <div class="flex justify-between"><span>Time</span><span>{{ $selectedOrder->paid_at?->format('H:i') }}</span></div>
                </div>
                <div class="space-y-2">
                    @foreach ($selectedOrder->items as $line)
                        <div>
                            <div class="flex justify-between gap-2"><span>{{ $line->quantity }} x {{ $line->item_description }}</span><span>RM{{ number_format((float) $line->subtotal, 2) }}</span></div>
                            @if (! empty($line->modifiers))
                                <p class="receipt-subline text-xs text-gray-500">{{ collect($line->modifiers)->pluck('name_en')->join(', ') }}</p>
                            @endif
                            @if ($line->notes)
                                <p class="receipt-subline text-xs text-gray-500">Note: {{ $line->notes }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-gray-300 pt-2 space-y-1">
                    <x-order-charges :order="$selectedOrder" />
                    <div class="flex justify-between font-bold"><span>Total</span><span>RM{{ number_format((float) $selectedOrder->total, 2) }}</span></div>
                    <div class="flex justify-between"><span>Payment</span><span>{{ $payment?->method?->description ?? '-' }}</span></div>
                    @if ($payment?->reference)
                        <div class="flex justify-between"><span>Reference</span><span>{{ $payment->reference }}</span></div>
                    @endif
                </div>
                <p class="text-center font-medium pt-2">{{ $settings['receipt_footer'] }}</p>
                <div class="flex justify-end gap-2 pt-3 print-hidden">
                    <button wire:click="closeReceipt" class="px-4 py-2 text-sm border border-gray-300 rounded">Close</button>
                    <button wire:click="markReceiptPrinted" onclick="window.print()" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded">Print</button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
