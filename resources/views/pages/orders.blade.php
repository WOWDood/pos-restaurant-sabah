<?php

use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Orders')] class extends Component {
    use WithPagination;

    public ?string $table = null;

    public string $status = 'all';

    public string $search = '';

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

    public function getOrdersProperty()
    {
        return Order::query()
            ->with('table')
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
                        <td class="px-3 py-2">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('pos.order', $order) }}" class="inline-flex items-center gap-1 bg-indigo-600 text-white text-xs font-medium px-3 py-1.5 rounded" wire:navigate>
                                <flux:icon.list-bullet class="w-3.5 h-3.5" />
                                Open
                            </a>
                            <a href="{{ route('pos.order', $order) }}" class="ml-2 text-xs text-gray-600 hover:underline" wire:navigate>View</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-gray-100">
                        <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">No orders found.</td>
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
</div>
