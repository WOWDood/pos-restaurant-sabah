<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('POS / Cashier')] class extends Component {
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

        $item = Item::query()
            ->where('active', true)
            ->with('modifierGroups.options')
            ->find($this->selectedItemId);

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

        OrderItem::create([
            'order_id' => $this->order->id,
            'item_id' => $item->id,
            'item_code' => $item->code,
            'item_description' => $item->description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($unitPrice * $quantity, 2),
            'notes' => $this->notes ?: null,
            'modifiers' => $modifiers ?: null,
        ]);

        $this->recalculateTotal();
        $this->resetAddItemForm();
        $this->addTab = 'search';
    }

    public function recalculateTotal(): void
    {
        $total = $this->order->items()->sum('subtotal');
        $this->order->update(['total' => $total]);
        $this->order->refresh()->load('table', 'items');
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

    private function resetAddItemForm(): void
    {
        $this->reset(['selectedItemId', 'quantity', 'notes', 'selections', 'itemSearch', 'categoryFilter']);
        $this->quantity = 1;
        $this->addTab = 'search';
        $this->resetValidation();
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">POS / Cashier</h1>

    @error('order')
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-2">{{ $message }}</div>
    @enderror

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
                <button wire:click="openAddItemModal" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">+ Add Item</button>
                <button class="bg-green-600 text-white text-sm font-medium px-4 py-2 rounded">Payment</button>
            @endif
            <a href="{{ route('orders', ['table' => $order->table->name ?? '']) }}" class="bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded">Orders ({{ \App\Models\Order::where('restaurant_table_id', $order->restaurant_table_id)->whereIn('status', \App\Models\Order::OPEN_STATUSES)->count() }})</a>
            <button class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">Print</button>
        </div>
        <div class="mt-3">
            <a href="{{ route('pos') }}" class="text-sm text-indigo-600 hover:underline">Back to Tables</a>
        </div>
    </x-card>

    <x-modal :show="$showAddItemModal" title="Add Item" close="closeAddItemModal" wide>
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
            @php($selectedItem = $this->selectedItem)
            @if ($selectedItem)
                <div class="space-y-4">
                    @php($primaryPhoto = $selectedItem->photos->firstWhere('is_primary', true) ?? $selectedItem->photos->first())
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
                                    <label class="flex items-center justify-between gap-3 bg-white border border-gray-200 rounded-md px-3 py-2 text-sm cursor-pointer hover:border-indigo-300">
                                        <span class="flex items-center gap-2">
                                            @if ($group->max_select === 1)
                                                <input
                                                    type="radio"
                                                    name="modifier-group-{{ $group->id }}"
                                                    value="{{ $option->id }}"
                                                    wire:model.live="selections.{{ $group->id }}"
                                                    class="text-indigo-600"
                                                />
                                            @else
                                                <input
                                                    type="checkbox"
                                                    value="{{ $option->id }}"
                                                    wire:model.live="selections.{{ $group->id }}"
                                                    class="rounded text-indigo-600"
                                                />
                                            @endif
                                            <span class="text-gray-800">{{ $option->name_en }}@if ($option->name_zh) / {{ $option->name_zh }}@endif</span>
                                        </span>
                                        @if ((float) $option->price_adjustment !== 0.0)
                                            <span class="text-xs text-gray-500 whitespace-nowrap">
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
                            Add to Order
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
</div>
