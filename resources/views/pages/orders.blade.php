<?php

use App\Models\BusinessSetting;
use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Orders')] class extends Component
{
    use WithPagination;

    public ?string $table = null;

    public string $status = 'all';

    public string $search = '';

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
        return BusinessSetting::query()->pluck('value', 'key');
    }

    public function getOrdersProperty()
    {
        return Order::query()
            ->with(['table', 'payments.method'])
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->search, fn ($query) => $query->whereHas('table', fn ($table) => $table->where('name', 'like', "%{$this->search}%")))
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function getUnpaidCountProperty(): int
    {
        return Order::query()
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->search, fn ($query) => $query->whereHas('table', fn ($table) => $table->where('name', 'like', "%{$this->search}%")))
            ->whereIn('status', Order::OPEN_STATUSES)
            ->count();
    }
}; ?>

<style>
    @media print {
        @page {
            size: 80mm auto;
            margin: 0;
        }

        html,
        body {
            width: 80mm !important;
            min-height: 0 !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
        }

        body * { visibility: hidden; }
        #receipt-print,
        #receipt-print * { visibility: visible; }
        .receipt-modal {
            position: static !important;
            inset: auto !important;
            display: block !important;
            width: auto !important;
            height: auto !important;
            background: transparent !important;
        }
        .receipt-modal .modal-card {
            position: static !important;
            display: block !important;
            width: auto !important;
            max-width: none !important;
            max-height: none !important;
            overflow: visible !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }
        .receipt-modal .modal-header,
        .receipt-modal .modal-body {
            position: static !important;
            max-height: none !important;
            overflow: visible !important;
        }
        .receipt-modal .modal-header { display: none !important; }
        .receipt-modal .modal-body { padding: 0 !important; }
        #receipt-print {
            position: absolute;
            left: 50%;
            top: 0;
            width: 74mm;
            max-width: 74mm;
            min-height: 0;
            margin: 0;
            padding: 4mm 3mm;
            box-sizing: border-box;
            transform: translateX(-50%);
            color: #000;
            background: #fff;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
            line-height: 1.35;
        }
        #receipt-print * {
            font-family: inherit !important;
        }
        #receipt-print .receipt-subline {
            display: block;
            padding-left: 2ch;
        }
        #receipt-print button,
        #receipt-print .print-hidden { display: none !important; }
    }
</style>

<div class="space-y-4">
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

        <div class="flex items-center gap-2 mb-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by table..." class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm" />
            <button class="bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded flex items-center gap-1">
                <flux:icon.magnifying-glass class="w-4 h-4" />
                Search
            </button>
        </div>

        <x-table>
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
                        <td class="px-3 py-2">#{{ $order->id }}</td>
                        <td class="px-3 py-2">{{ $order->table->name ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ Order::statusBadgeClasses($order->status) }}">
                                {{ Order::statusLabel($order->status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-3 py-2">{{ $order->payments->sortByDesc('id')->first()?->method?->description ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('pos.order', $order) }}" class="inline-flex items-center gap-1 bg-indigo-600 text-white text-xs font-medium px-3 py-1.5 rounded" wire:navigate>
                                <flux:icon.list-bullet class="w-3.5 h-3.5" />
                                Open
                            </a>
                            <button wire:click="openOrderDetail({{ $order->id }})" class="ml-2 text-xs text-gray-600 hover:underline">View</button>
                            @if ($order->status === Order::STATUS_PAID)
                                <button wire:click="openReceipt({{ $order->id }})" class="ml-2 text-xs text-indigo-600 hover:underline">Print</button>
                            @endif
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
                                        <p class="text-xs text-gray-500">{{ collect($line->modifiers)->pluck('name_en')->join(', ') }}</p>
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
                    <p>{{ $settings['location'] ?? '' }}</p>
                    <p>{{ $settings['phone'] ?? '' }}</p>
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
                    <div class="flex justify-between font-bold"><span>Total</span><span>RM{{ number_format((float) $selectedOrder->total, 2) }}</span></div>
                    <div class="flex justify-between"><span>Payment</span><span>{{ $payment?->method?->description ?? '-' }}</span></div>
                    @if ($payment?->reference)
                        <div class="flex justify-between"><span>Reference</span><span>{{ $payment->reference }}</span></div>
                    @endif
                </div>
                <p class="text-center font-medium pt-2">Thank you for your visit!</p>
                <div class="flex justify-end gap-2 pt-3 print-hidden">
                    <button wire:click="closeReceipt" class="px-4 py-2 text-sm border border-gray-300 rounded">Close</button>
                    <button wire:click="markReceiptPrinted" onclick="window.print()" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded">Print</button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
