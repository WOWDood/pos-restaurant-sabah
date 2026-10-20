<?php

use App\Models\Order;
use App\Models\RestaurantTable;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('POS / Cashier')] class extends Component {
    use WithPagination;

    public string $search = '';
    public ?int $selectedTableId = null;
    public bool $showTableModal = false;
    public bool $showCreateModal = false;

    public function updatingSearch(): void { $this->resetPage(); }

    public function getTablesProperty()
    {
        return RestaurantTable::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get()
            ->map(function ($table) {
                $table->open_orders_count = $table->orders()
                    ->whereIn('status', Order::OPEN_STATUSES)
                    ->count();
                return $table;
            });
    }

    public function openTable(int $id): void
    {
        $this->selectedTableId = $id;
        $table = RestaurantTable::findOrFail($id);
        $openCount = $table->orders()->whereIn('status', Order::OPEN_STATUSES)->count();
        if ($openCount === 0) {
            $this->showCreateModal = true;
        } else {
            $this->showTableModal = true;
        }
    }

    public function viewOpenOrders(): void
    {
        $table = RestaurantTable::find($this->selectedTableId);
        $this->redirect(route('orders', ['table' => $table->name]));
    }

    public function confirmCreate(): void
    {
        $this->showCreateModal = true;
        $this->showTableModal = false;
    }

    public function createOrder(): void
    {
        $order = Order::create([
            'restaurant_table_id' => $this->selectedTableId,
            'status' => Order::STATUS_NEW,
            'total' => 0,
            'created_by' => auth()->id(),
        ]);
        $this->showCreateModal = false;
        $this->redirect(route('pos.order', $order));
    }

    public function cancelTable(): void
    {
        $this->showTableModal = false;
        $this->showCreateModal = false;
        $this->selectedTableId = null;
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">POS / Cashier</h1>

    <x-card>
        <div class="flex items-center gap-3 mb-4">
            <input type="text" wire:model.live="search" placeholder="Search tables..." class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm" />
            <button class="bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded">Search</button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" wire:poll.10s>
            @foreach ($this->tables as $table)
                <div wire:click="openTable({{ $table->id }})" class="cursor-pointer bg-white border border-gray-200 rounded-lg p-4 text-center hover:shadow">
                    <p class="font-semibold text-gray-900 mb-2">{{ $table->name }}</p>
                    @if ($table->open_orders_count > 0)
                        <span class="inline-block bg-yellow-100 text-yellow-700 text-xs font-medium px-2 py-1 rounded">
                            {{ $table->open_orders_count }} order(s)
                        </span>
                    @else
                        <span class="text-sm text-gray-500">Available</span>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- Table action modal --}}
    <x-modal :show="$showTableModal" title="{{ \App\Models\RestaurantTable::find($selectedTableId)?->name }}" close="$set('showTableModal', false)">
        <p class="text-sm text-amber-600 mb-4">{{ \App\Models\RestaurantTable::find($selectedTableId)?->orders()->whereIn('status', \App\Models\Order::OPEN_STATUSES)->count() ?? 0 }} open order(s) found</p>
        <p class="text-sm text-gray-500 mb-4">What would you like to do?</p>
        <div class="space-y-2">
            <button wire:click="viewOpenOrders" class="w-full bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">View Open Orders</button>
            <button wire:click="confirmCreate" class="w-full bg-white border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded">+ Create New Order</button>
            <button wire:click="cancelTable" class="w-full bg-white border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded">Cancel</button>
        </div>
    </x-modal>

    {{-- Create new order confirmation --}}
    <x-modal :show="$showCreateModal" title="{{ \App\Models\RestaurantTable::find($selectedTableId)?->name }}" close="$set('showCreateModal', false)">
        <p class="text-sm text-gray-500 mb-4">Create a new order for this table?</p>
        <div class="flex justify-end gap-2">
            <button wire:click="cancelTable" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="createOrder" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded">Create New Order</button>
        </div>
    </x-modal>
</div>