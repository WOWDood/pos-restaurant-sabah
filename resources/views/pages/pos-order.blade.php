<?php

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('POS / Cashier')] class extends Component
{
    public Order $order;

    public bool $showAddItemModal = false;

    public string $addTab = 'search';

    public string $itemSearch = '';

    public ?int $categoryFilter = null;

    public ?int $selectedItemId = null;

    public int $quantity = 1;

    public string $notes = '';

    /** @var array<int, int|string|array<int, int|string>> */
    public array $selections = [];

    public ?int $editingLineId = null;

    public ?int $removingLineId = null;

    public bool $showPaymentModal = false;

    public bool $showConfirmPaymentModal = false;

    public ?int $selectedPaymentMethodId = null;

    public string $paymentReference = '';

    public ?string $paymentSuccess = null;

    public bool $showReceiptModal = false;

    public function mount(Order $order): void
    {
        $this->order = $order->load('table', 'items');
    }

    public function getCanEditProperty(): bool
    {
        return $this->order->isEditable();
    }

    public function getCategoriesProperty()
    {
        return Category::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function getAvailableItemsProperty()
    {
        return Item::query()
            ->where('active', true)
            ->when($this->itemSearch, function ($query) {
                $search = $this->itemSearch;
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->orderBy('code')
            ->get();
    }

    public function getSelectedItemProperty(): ?Item
    {
        if (! $this->selectedItemId) {
            return null;
        }

        return Item::query()
            ->with(['photos', 'modifierGroups.options'])
            ->find($this->selectedItemId);
    }

    public function getPreviewUnitPriceProperty(): float
    {
        $item = $this->selectedItem;
        if (! $item) {
            return 0;
        }

        $unitPrice = (float) $item->unit_price;

        foreach ($item->modifierGroups as $group) {
            $selectedIds = $this->selectedOptionIds($group);
            foreach ($group->options as $option) {
                if (in_array($option->id, $selectedIds, true)) {
                    $unitPrice += (float) $option->price_adjustment;
                }
            }
        }

        return round($unitPrice, 2);
    }

    public function getPreviewItemPriceProperty(): float
    {
        return round($this->previewUnitPrice * max(1, $this->quantity), 2);
    }

    public function getRequiredGroupsSatisfiedProperty(): bool
    {
        $item = $this->selectedItem;
        if (! $item) {
            return false;
        }

        foreach ($item->modifierGroups as $group) {
            $count = count($this->selectedOptionIds($group));
            if ($count < $group->min_select || $count > $group->max_select) {
                return false;
            }
        }

        return true;
    }

    public function openAddItemModal(): void
    {
        if (! $this->ensureEditable()) {
            return;
        }

        $this->resetAddItemForm();
        $this->showAddItemModal = true;
    }

    public function closeAddItemModal(): void
    {
        $this->showAddItemModal = false;
        $this->resetAddItemForm();
    }

    public function setAddTab(string $tab): void
    {
        if (! in_array($tab, ['search', 'selected'], true)) {
            return;
        }

        if ($tab === 'selected' && ! $this->selectedItemId) {
            return;
        }

        $this->addTab = $tab;
    }

    public function selectItem(int $itemId): void
    {
        $item = Item::query()
            ->where('active', true)
            ->with('modifierGroups.options')
            ->findOrFail($itemId);

        $this->selectedItemId = $item->id;
        $this->quantity = 1;
        $this->notes = '';
        $this->selections = [];
        $this->addTab = 'selected';
        $this->resetValidation();
    }

    public function editLine(int $lineId): void
    {
        if (! $this->ensureEditable()) {
            return;
        }

        $line = $this->order->items()->findOrFail($lineId);
        $item = Item::query()
            ->with('modifierGroups.options')
            ->find($line->item_id);

        if (! $item) {
            $this->addError('order', 'The item on this line is no longer available.');

            return;
        }

        $this->resetValidation();
        $this->editingLineId = $line->id;
        $this->selectedItemId = $item->id;
        $this->quantity = $line->quantity;
        $this->notes = $line->notes ?? '';
        $this->selections = [];

        $modifiersByGroup = collect($line->modifiers ?? [])->groupBy('group_id');

        foreach ($item->modifierGroups as $group) {
            $optionIds = $modifiersByGroup->get($group->id, collect())
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            $this->selections[$group->id] = $group->max_select === 1
                ? ($optionIds[0] ?? null)
                : $optionIds;
        }

        $this->addTab = 'selected';
        $this->showAddItemModal = true;
    }

    public function confirmRemove(int $lineId): void
    {
        if (! $this->ensureEditable()) {
            return;
        }

        $this->order->items()->findOrFail($lineId);
        $this->removingLineId = $lineId;
    }

    public function cancelRemove(): void
    {
        $this->removingLineId = null;
    }

    public function removeLine(): void
    {
        if (! $this->ensureEditable()) {
            return;
        }

        if (! $this->removingLineId) {
            return;
        }

        $line = $this->order->items()->findOrFail($this->removingLineId);
        $line->delete();

        $this->removingLineId = null;
        $this->recalculateTotal();
    }

    public function addToOrder(): void
    {
        if (! $this->ensureEditable()) {
            return;
        }

        $this->validate([
            'selectedItemId' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        $itemQuery = Item::query()
            ->with('modifierGroups.options')
            ->whereKey($this->selectedItemId);

        if (! $this->editingLineId) {
            $itemQuery->where('active', true);
        }

        $item = $itemQuery->first();

        if (! $item) {
            $this->addError('selectedItemId', 'This item is no longer available.');
            $this->addTab = 'selected';

            return;
        }

        $modifiers = [];
        $unitPrice = (float) $item->unit_price;
        $hasErrors = false;

        foreach ($item->modifierGroups as $group) {
            $selectedIds = $this->selectedOptionIds($group);
            $count = count($selectedIds);

            if ($count < $group->min_select) {
                $this->addError("selections.{$group->id}", "{$group->name}: choose at least {$group->min_select} option(s).");
                $hasErrors = true;
            }

            if ($count > $group->max_select) {
                $this->addError("selections.{$group->id}", "{$group->name}: choose at most {$group->max_select} option(s).");
                $hasErrors = true;
            }

            foreach ($group->options as $option) {
                if (! in_array($option->id, $selectedIds, true)) {
                    continue;
                }

                $unitPrice += (float) $option->price_adjustment;
                $modifiers[] = [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'option_id' => $option->id,
                    'name_en' => $option->name_en,
                    'name_zh' => $option->name_zh,
                    'price_adjustment' => (float) $option->price_adjustment,
                ];
            }
        }

        if ($hasErrors) {
            $this->addTab = 'selected';

            return;
        }

        $unitPrice = round($unitPrice, 2);
        $quantity = max(1, (int) $this->quantity);

        $lineData = [
            'item_id' => $item->id,
            'item_code' => $item->code,
            'item_description' => $item->description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($unitPrice * $quantity, 2),
            'notes' => $this->notes ?: null,
            'modifiers' => $modifiers ?: null,
        ];

        $wasEditing = (bool) $this->editingLineId;

        if ($wasEditing) {
            $this->order->items()->findOrFail($this->editingLineId)->update($lineData);
        } else {
            $this->order->items()->create($lineData);
        }

        $this->recalculateTotal();
        $this->resetAddItemForm();

        if ($wasEditing) {
            $this->showAddItemModal = false;
        } else {
            $this->addTab = 'search';
        }
    }

    public function recalculateTotal(): void
    {
        $total = $this->order->items()->sum('subtotal');
        $this->order->update(['total' => $total]);
        $this->order->refresh()->load('table', 'items');
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getSelectedPaymentMethodProperty(): ?PaymentMethod
    {
        return $this->selectedPaymentMethod();
    }

    public function getBusinessSettingsProperty()
    {
        return BusinessSetting::query()->pluck('value', 'key');
    }

    public function openPaymentModal(): void
    {
        if (! $this->ensurePayable()) {
            return;
        }

        $this->resetPaymentForm();
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->resetPaymentForm();
    }

    public function selectPaymentMethod(int $paymentMethodId): void
    {
        $method = PaymentMethod::query()
            ->whereKey($paymentMethodId)
            ->where('active', true)
            ->first();

        if (! $method) {
            $this->addError('payment', 'This payment method is not available.');

            return;
        }

        $this->selectedPaymentMethodId = $method->id;
        $this->paymentReference = '';
        $this->resetValidation();
    }

    public function confirmPayment(): void
    {
        if (! $this->ensurePayable()) {
            return;
        }

        $method = $this->selectedPaymentMethod();

        if (! $method) {
            $this->addError('payment', 'Choose a payment method.');

            return;
        }

        if ($method->ref_required && trim($this->paymentReference) === '') {
            $this->addError('paymentReference', 'Reference is required for this payment method.');

            return;
        }

        $this->resetValidation();
        $this->showPaymentModal = false;
        $this->showConfirmPaymentModal = true;
    }

    public function cancelConfirmPayment(): void
    {
        $this->showConfirmPaymentModal = false;
        $this->showPaymentModal = true;
    }

    public function payNow(): void
    {
        $payment = DB::transaction(function (): ?Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($this->order->id);

            if (! $order->isOpen()) {
                $this->addError('payment', 'This order has already been paid or cancelled.');

                return null;
            }

            $method = PaymentMethod::query()
                ->whereKey($this->selectedPaymentMethodId)
                ->where('active', true)
                ->first();

            if (! $method) {
                $this->addError('payment', 'This payment method is not available.');

                return null;
            }

            if ($method->ref_required && trim($this->paymentReference) === '') {
                $this->addError('paymentReference', 'Reference is required for this payment method.');

                return null;
            }

            $total = round((float) $order->items()->sum('subtotal'), 2);

            if ($total <= 0) {
                $this->addError('payment', 'An order must have at least one item before payment.');

                return null;
            }

            $payment = $order->payments()->create([
                'payment_method_id' => $method->id,
                'amount' => $total,
                'reference' => trim($this->paymentReference) ?: null,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $order->update([
                'status' => Order::STATUS_PAID,
                'total' => $total,
                'paid_at' => now(),
            ]);

            Receipt::create([
                'order_id' => $order->id,
                'receipt_number' => 'RCPT-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $payment;
        });

        if (! $payment) {
            return;
        }

        $this->order = $payment->order->load('table', 'items', 'payments.method', 'receipt');
        $this->showConfirmPaymentModal = false;
        $this->resetPaymentForm();
        $this->showReceiptModal = true;
        $this->paymentSuccess = 'Payment completed successfully.';
    }

    public function closeReceiptModal(): void
    {
        $this->showReceiptModal = false;
    }

    public function openReceipt(): void
    {
        if ($this->order->status !== Order::STATUS_PAID) {
            $this->addError('order', 'Receipts are available for paid orders only.');

            return;
        }

        $this->order->load('table', 'items', 'payments.method', 'receipt');
        $this->showReceiptModal = true;
    }

    public function markReceiptPrinted(): void
    {
        if (! $this->order->receipt) {
            return;
        }

        $this->order->receipt->update(['printed_at' => now()]);
    }

    /**
     * @return array<int, int>
     */
    private function selectedOptionIds(\App\Models\ModifierGroup $group): array
    {
        $raw = $this->selections[$group->id] ?? [];
        $raw = is_array($raw) ? $raw : [$raw];

        $validIds = $group->options->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selected = array_map('intval', $raw);

        return array_values(array_unique(array_intersect($selected, $validIds)));
    }

    private function ensureEditable(): bool
    {
        if (! $this->order->isEditable()) {
            $this->addError('order', 'Paid or cancelled orders cannot be modified.');

            return false;
        }

        return true;
    }

    private function ensurePayable(): bool
    {
        if (! $this->order->isOpen()) {
            $this->addError('payment', 'Paid or cancelled orders cannot be paid.');

            return false;
        }

        if ($this->order->items()->count() === 0) {
            $this->addError('payment', 'An order must have at least one item before payment.');

            return false;
        }

        return true;
    }

    private function selectedPaymentMethod(): ?PaymentMethod
    {
        if (! $this->selectedPaymentMethodId) {
            return null;
        }

        return PaymentMethod::query()
            ->whereKey($this->selectedPaymentMethodId)
            ->where('active', true)
            ->first();
    }

    private function resetPaymentForm(): void
    {
        $this->reset(['selectedPaymentMethodId', 'paymentReference']);
        $this->resetValidation();
    }

    private function resetAddItemForm(): void
    {
        $this->reset(['selectedItemId', 'quantity', 'notes', 'selections', 'itemSearch', 'categoryFilter', 'editingLineId']);
        $this->quantity = 1;
        $this->addTab = 'search';
        $this->resetValidation();
    }
}; ?>

<style>
    @media print {
        body * { visibility: hidden; }
        .receipt-print, .receipt-print * { visibility: visible; }
        .receipt-print {
            position: absolute;
            left: 0;
            top: 0;
            width: 80mm;
            margin: 0;
            padding: 4mm;
            color: #000;
            background: #fff;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
        }
        .receipt-print button { display: none !important; }
    }
</style>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">POS / Cashier</h1>

    @error('order')
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-2">{{ $message }}</div>
    @enderror
    @if ($paymentSuccess)
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-2">{{ $paymentSuccess }}</div>
    @endif

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
                    <th class="text-left px-3 py-2">Qty</th>
                    <th class="text-left px-3 py-2">Subtotal</th>
                    <th class="text-right px-3 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->items as $i => $line)
                    <tr class="border-t border-gray-100 align-top">
                        <td class="px-3 py-2">{{ $i + 1 }}</td>
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-900">{{ $line->item_code }} - {{ $line->item_description }}</p>
                            @if (! empty($line->modifiers))
                                <div class="text-xs text-gray-500 mt-1 space-y-0.5">
                                    @foreach (collect($line->modifiers)->groupBy('group_name') as $groupName => $modifiers)
                                        <p>
                                            <span class="font-medium">{{ $groupName }}:</span>
                                            {{ $modifiers->map(fn ($modifier) => $modifier['name_en'] . (! empty($modifier['name_zh']) ? ' / ' . $modifier['name_zh'] : ''))->join(', ') }}
                                        </p>
                                    @endforeach
                                </div>
                            @endif
                            @if ($line->notes)
                                <p class="text-xs italic text-gray-500 mt-1">Note: {{ $line->notes }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">RM{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="px-3 py-2">{{ $line->quantity }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">RM{{ number_format((float) $line->subtotal, 2) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($this->canEdit)
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="editLine({{ $line->id }})" class="text-blue-600 hover:underline">Edit</button>
                                    <button wire:click="confirmRemove({{ $line->id }})" class="text-red-600 hover:underline">Remove</button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-gray-100">
                        <td colspan="6" class="px-3 py-6 text-center text-sm text-gray-500">No items yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="flex gap-2">
            @if ($this->canEdit)
                <button wire:click="openAddItemModal" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">+ Add Item</button>
                <button
                    wire:click="openPaymentModal"
                    @disabled($order->items->isEmpty())
                    class="bg-green-600 text-white text-sm font-medium px-4 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Payment
                </button>
            @endif
            <a href="{{ route('orders', ['table' => $order->table->name ?? '']) }}" class="bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded">Orders ({{ \App\Models\Order::where('restaurant_table_id', $order->restaurant_table_id)->whereIn('status', \App\Models\Order::OPEN_STATUSES)->count() }})</a>
            @if ($order->status === Order::STATUS_PAID)
                <button wire:click="openReceipt" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">Print</button>
            @endif
        </div>
        <div class="mt-3">
            <a href="{{ route('pos') }}" class="text-sm text-indigo-600 hover:underline">Back to Tables</a>
        </div>
    </x-card>

    <x-modal :show="$showAddItemModal" title="{{ $editingLineId ? 'Edit Item' : 'Add Item' }}" close="closeAddItemModal" wide>
        <div class="flex border-b border-gray-200 -mt-2 mb-5">
            <button
                wire:click="setAddTab('search')"
                class="flex-1 pb-3 text-sm font-medium border-b-2 {{ $addTab === 'search' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >
                Search Item
            </button>
            <button
                wire:click="setAddTab('selected')"
                class="flex-1 pb-3 text-sm font-medium border-b-2 flex items-center justify-center gap-1 {{ $addTab === 'selected' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
            >
                Selected Item
                @if ($selectedItemId)
                    <span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-indigo-100 text-indigo-600 text-xs">{{ $selectedItemId ? 1 : 0 }}</span>
                @endif
            </button>
        </div>

        @if ($addTab === 'search')
            <div class="space-y-4">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="itemSearch" placeholder="Search items..." class="w-full rounded border border-gray-300 px-3 py-2 pr-10 text-sm" />
                    <flux:icon.magnifying-glass class="w-4 h-4 text-gray-400 absolute right-3 top-2.5" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        wire:click="$set('categoryFilter', null)"
                        class="px-4 py-1.5 rounded-full text-sm {{ $categoryFilter === null ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    >
                        All
                    </button>
                    @foreach ($this->categories as $category)
                        <button
                            wire:click="$set('categoryFilter', {{ $category->id }})"
                            class="px-4 py-1.5 rounded-full text-sm {{ $categoryFilter === $category->id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                        >
                            {{ $category->description }}
                        </button>
                    @endforeach
                </div>

                <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                    @forelse ($this->availableItems as $item)
                        <button
                            type="button"
                            wire:key="available-item-{{ $item->id }}"
                            wire:click="selectItem({{ $item->id }})"
                            class="w-full flex items-center justify-between gap-4 px-4 py-3 text-left hover:bg-gray-50"
                        >
                            <span class="text-sm text-gray-900">{{ $item->code }} - {{ $item->description }}</span>
                            <span class="text-sm text-gray-700 whitespace-nowrap">RM{{ number_format((float) $item->unit_price, 2) }}</span>
                        </button>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-gray-500">No active items found.</p>
                    @endforelse
                </div>
            </div>
        @else
            @php
                $selectedItem = $this->selectedItem;
            @endphp
            @if ($selectedItem)
                <div class="space-y-4">
                    @php
                        $primaryPhoto = $selectedItem->photos->firstWhere('is_primary', true) ?? $selectedItem->photos->first();
                    @endphp
                    <div class="flex gap-4 bg-indigo-50 rounded-lg p-4">
                        @if ($primaryPhoto)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($primaryPhoto->path) }}" alt="{{ $selectedItem->description }}" class="w-28 h-24 object-cover rounded-md border border-white shadow-sm" />
                        @else
                            <div class="w-28 h-24 rounded-md bg-white border border-indigo-100 flex items-center justify-center text-indigo-300">
                                <flux:icon.photo class="w-8 h-8" />
                            </div>
                        @endif
                        <div class="self-end pb-1">
                            <p class="font-semibold text-gray-900">{{ $selectedItem->code }} - {{ $selectedItem->description }}</p>
                            <p class="text-sm text-indigo-600 mt-1">RM{{ number_format((float) $selectedItem->unit_price, 2) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                            <input type="number" min="1" wire:model.live="quantity" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                            @error('quantity') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <input type="text" wire:model="notes" placeholder="Special instructions..." class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                            @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @foreach ($selectedItem->modifierGroups as $group)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-semibold text-sm text-gray-900">{{ $group->name }}</span>
                                @if ($group->min_select > 0)
                                    <span class="bg-red-100 text-red-600 text-xs font-medium px-2 py-0.5 rounded">
                                        Required - Pick {{ $group->min_select === $group->max_select ? $group->min_select : $group->min_select . '-' . $group->max_select }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-500">Optional</span>
                                @endif
                            </div>
                            <div class="space-y-2">
                                @foreach ($group->options as $option)
                                    <label class="inline-flex items-center justify-between gap-3 rounded-full border px-3 py-2 text-sm cursor-pointer transition {{ in_array((int) $option->id, $this->selectedOptionIds($group), true) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-200 bg-white text-gray-800 hover:border-indigo-300' }}">
                                        @if ($group->max_select === 1)
                                            <input
                                                type="radio"
                                                name="modifier-group-{{ $group->id }}"
                                                value="{{ $option->id }}"
                                                wire:model.live="selections.{{ $group->id }}"
                                                class="sr-only"
                                            />
                                        @else
                                            <input
                                                type="checkbox"
                                                value="{{ $option->id }}"
                                                wire:model.live="selections.{{ $group->id }}"
                                                class="sr-only"
                                            />
                                        @endif
                                        <span>{{ $option->name_en }}@if ($option->name_zh) / {{ $option->name_zh }}@endif</span>
                                        @if ((float) $option->price_adjustment !== 0.0)
                                            <span class="text-xs whitespace-nowrap {{ in_array((int) $option->id, $this->selectedOptionIds($group), true) ? 'text-indigo-100' : 'text-gray-500' }}">
                                                {{ (float) $option->price_adjustment > 0 ? '+' : '-' }}RM{{ number_format(abs((float) $option->price_adjustment), 2) }}
                                            </span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            @error("selections.{$group->id}") <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
                        </div>
                    @endforeach

                    <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Item Price:</span>
                            <span class="font-medium text-gray-900">RM{{ number_format($this->previewItemPrice, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-600">Unit Price:</span>
                            <span class="font-medium text-gray-900">RM{{ number_format($this->previewUnitPrice, 2) }}</span>
                        </div>
                    </div>

                    @error('selectedItemId') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="flex justify-end">
                        <button
                            wire:click="addToOrder"
                            @disabled(! $this->requiredGroupsSatisfied)
                            class="bg-indigo-600 text-white text-sm font-medium px-6 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ $editingLineId ? 'Update Item' : 'Add to Order' }}
                        </button>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-8">Select an item from the Search Item tab first.</p>
            @endif
        @endif

        <div class="flex justify-end border-t border-gray-200 mt-5 pt-4">
            <button wire:click="closeAddItemModal" class="px-5 py-2 text-sm border border-gray-300 rounded">Done</button>
        </div>
    </x-modal>

    <x-modal :show="(bool) $removingLineId" title="Remove Item" close="cancelRemove">
        <p class="text-sm text-gray-700 mb-4">Remove this item from the order?</p>
        @error('order') <p class="text-xs text-red-600 mb-2">{{ $message }}</p> @enderror
        <div class="flex justify-end gap-2">
            <button wire:click="cancelRemove" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="removeLine" class="px-4 py-2 text-sm bg-red-600 text-white rounded">Remove</button>
        </div>
    </x-modal>

    <x-modal :show="$showPaymentModal" title="Payment" close="closePaymentModal">
        <div class="space-y-5">
            @error('payment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                <span class="text-sm text-gray-600">Total Amount</span>
                <span class="text-lg font-bold text-gray-900">RM{{ number_format((float) $order->total, 2) }}</span>
            </div>

            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Payment Method</p>
                <div class="flex flex-wrap gap-2">
                    @forelse ($this->paymentMethods as $method)
                        <button
                            type="button"
                            wire:click="selectPaymentMethod({{ $method->id }})"
                            class="rounded-full border px-4 py-2 text-sm {{ $selectedPaymentMethodId === $method->id ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 text-gray-700 hover:border-indigo-400' }}"
                        >
                            {{ $method->description }}
                        </button>
                    @empty
                        <p class="text-sm text-gray-500">No active payment methods are available.</p>
                    @endforelse
                </div>
                @error('payment') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
            </div>

            @if ($this->selectedPaymentMethod?->ref_required)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
                    <input type="text" wire:model="paymentReference" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    @error('paymentReference') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                <button wire:click="closePaymentModal" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
                <button wire:click="confirmPayment" class="px-4 py-2 text-sm bg-green-600 text-white rounded">Continue</button>
            </div>
        </div>
    </x-modal>

    <x-modal :show="$showConfirmPaymentModal" title="Confirm Payment" close="cancelConfirmPayment">
        @php
            $selectedPaymentMethod = $this->selectedPaymentMethod;
        @endphp
        <div class="space-y-4">
            @error('payment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Total</span><span class="font-semibold">RM{{ number_format((float) $order->total, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Method</span><span>{{ $selectedPaymentMethod?->description ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Reference</span><span>{{ $paymentReference ?: '-' }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Order/Table</span><span>#{{ $order->id }} / {{ $order->table->name ?? '-' }}</span></div>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                <button wire:click="cancelConfirmPayment" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
                <button wire:click="payNow" class="px-4 py-2 text-sm bg-green-600 text-white rounded">Yes, Pay Now</button>
            </div>
        </div>
    </x-modal>

    <x-modal :show="$showReceiptModal" title="Receipt" close="closeReceiptModal" wide>
        @php
            $receipt = $order->receipt;
            $payment = $order->payments->sortByDesc('id')->first();
            $settings = $this->businessSettings;
        @endphp
        <div class="receipt-print space-y-3 text-sm">
            <div class="text-center">
                <p class="text-lg font-bold">{{ $settings['business_name'] ?? 'Kedai Soto Banso' }}</p>
                <p>{{ $settings['location'] ?? '' }}</p>
                <p>{{ $settings['phone'] ?? '' }}</p>
            </div>
            <div class="border-t border-b border-gray-300 py-2 space-y-1">
                <div class="flex justify-between"><span>Receipt</span><span>{{ $receipt?->receipt_number ?? '-' }}</span></div>
                <div class="flex justify-between"><span>Order #</span><span>{{ $order->id }}</span></div>
                <div class="flex justify-between"><span>Table</span><span>{{ $order->table->name ?? '-' }}</span></div>
                <div class="flex justify-between"><span>Date</span><span>{{ $order->paid_at?->format('d M Y') }}</span></div>
                <div class="flex justify-between"><span>Time</span><span>{{ $order->paid_at?->format('H:i') }}</span></div>
            </div>
            <div class="space-y-2">
                @foreach ($order->items as $line)
                    <div>
                        <div class="flex justify-between gap-2">
                            <span>{{ $line->quantity }} x {{ $line->item_description }}</span>
                            <span>RM{{ number_format((float) $line->subtotal, 2) }}</span>
                        </div>
                        @if (! empty($line->modifiers))
                            <p class="text-xs text-gray-500">{{ collect($line->modifiers)->pluck('name_en')->join(', ') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="border-t border-gray-300 pt-2 space-y-1">
                <div class="flex justify-between font-bold"><span>Total</span><span>RM{{ number_format((float) $order->total, 2) }}</span></div>
                <div class="flex justify-between"><span>Payment</span><span>{{ $payment?->method?->description ?? '-' }}</span></div>
                @if ($payment?->reference)
                    <div class="flex justify-between"><span>Reference</span><span>{{ $payment->reference }}</span></div>
                @endif
            </div>
            <p class="text-center font-medium pt-2">Thank you for your visit!</p>
            <div class="flex justify-end gap-2 pt-3 print-hidden">
                <button wire:click="closeReceiptModal" class="px-4 py-2 text-sm border border-gray-300 rounded">Close</button>
                <button wire:click="markReceiptPrinted" onclick="window.print()" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded">Print</button>
            </div>
        </div>
    </x-modal>
</div>
