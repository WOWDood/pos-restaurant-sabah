<?php

use App\Models\Order;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Orders')] class extends Component {
    use WithPagination;

    public ?string $table = null;

    public function mount(): void
    {
        $this->table = request()->query('table');
    }

    public function getOrdersProperty()
    {
        return Order::query()
            ->with('table')
            ->when($this->table, fn ($q) => $q->whereHas('table', fn ($t) => $t->where('name', $this->table)))
            ->orderByDesc('id')
            ->paginate(10);
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">Orders</h1>

    <x-card>
        @if ($table)
            <div class="mb-3 text-sm text-gray-600">Filtered by table: {{ $table }}</div>
        @endif
        <x-table>
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Order #</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Table</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Status</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Total</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Created</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->orders as $order)
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2">#{{ $order->id }}</td>
                        <td class="px-3 py-2">{{ $order->table->name ?? '-' }}</td>
                        <td class="px-3 py-2">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</td>
                        <td class="px-3 py-2">RM{{ number_format((float)$order->total, 2) }}</td>
                        <td class="px-3 py-2">{{ $order->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </x-card>
</div>