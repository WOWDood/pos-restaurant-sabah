<?php

use App\Models\Order;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('POS / Cashier')] class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order->load('table', 'items');
    }

    public function getCanEditProperty(): bool
    {
        return $this->order->isEditable();
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">POS / Cashier</h1>

    <x-card>
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm text-gray-500">Table: {{ $order->table->name ?? '-' }}</p>
                <p class="text-sm font-medium text-gray-900">Order #{{ $order->id }}</p>
                <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium {{ Order::statusBadgeClasses($order->status) }}">{{ Order::statusLabel($order->status) }}</span>
            </div>
            <p class="text-lg font-bold text-gray-900">RM{{ number_format((float) $order->total, 2) }}</p>
        </div>

        <table class="w-full text-sm mb-4">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-3 py-2">#</th>
                    <th class="text-left px-3 py-2">Item</th>
                    <th class="text-left px-3 py-2">Price</th>
                    <th class="text-left px-3 py-2">Subtotal</th>
                    <th class="text-right px-3 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->items as $i => $line)
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2">{{ $i + 1 }}</td>
                        <td class="px-3 py-2">{{ $line->item_description }}</td>
                        <td class="px-3 py-2">RM{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="px-3 py-2">RM{{ number_format((float) $line->subtotal, 2) }}</td>
                        <td class="px-3 py-2 text-right">
                            @if ($this->canEdit)
                                <span class="text-gray-400">Edit | Remove</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-gray-100">
                        <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">No items yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex gap-2">
            @if ($this->canEdit)
                <button class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">+ Add Item</button>
                <button class="bg-green-600 text-white text-sm font-medium px-4 py-2 rounded">Payment</button>
            @endif
            <a href="{{ route('orders', ['table' => $order->table->name ?? '']) }}" class="bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded">Orders ({{ \App\Models\Order::where('restaurant_table_id', $order->restaurant_table_id)->whereIn('status', \App\Models\Order::OPEN_STATUSES)->count() }})</a>
            <button class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">Print</button>
        </div>
        <div class="mt-3">
            <a href="{{ route('pos') }}" class="text-sm text-indigo-600 hover:underline">Back to Tables</a>
        </div>
    </x-card>
</div>
