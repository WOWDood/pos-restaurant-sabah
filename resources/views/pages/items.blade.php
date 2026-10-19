<?php

use App\Models\Category;
use App\Models\Item;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Item Search')] class extends Component {
    use WithPagination;

    public string $search = '';
    public ?int $categoryFilter = null; // null = All
    public string $code = '';
    public ?int $category_id = null;
    public string $description = '';
    public string $notes = '';
    public float $unit_price = 0;
    public bool $active = true;
    public bool $is_combo = false;
    public ?int $editingId = null;
    public bool $showModal = false;
    public ?int $deleteId = null;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingCategoryFilter(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['code', 'category_id', 'description', 'notes', 'unit_price', 'active', 'is_combo', 'editingId']);
        $this->active = true;
        $this->unit_price = 0;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $item = Item::findOrFail($id);
        $this->editingId = $item->id;
        $this->code = $item->code;
        $this->category_id = $item->category_id;
        $this->description = $item->description;
        $this->notes = $item->notes ?? '';
        $this->unit_price = (float) $item->unit_price;
        $this->active = (bool) $item->active;
        $this->is_combo = (bool) $item->is_combo;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'code' => 'required|string|max:50|unique:items,code' . ($this->editingId ? ',' . $this->editingId : ''),
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:255',
            'unit_price' => 'required|numeric|min:0',
        ]);

        $data = [
            'code' => $this->code,
            'category_id' => $this->category_id,
            'description' => $this->description,
            'notes' => $this->notes ?: null,
            'unit_price' => $this->unit_price,
            'active' => $this->active,
            'is_combo' => $this->is_combo,
            'type' => $this->is_combo ? 'Combo' : 'General',
        ];

        if ($this->editingId) {
            Item::findOrFail($this->editingId)->update($data);
        } else {
            Item::create($data);
        }

        $this->showModal = false;
        $this->reset(['code', 'category_id', 'description', 'notes', 'unit_price', 'active', 'is_combo', 'editingId']);
        $this->active = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            Item::findOrFail($this->deleteId)->delete();
        }
        $this->deleteId = null;
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }

    public function getItemsProperty()
    {
        return Item::query()
            ->with('category')
            ->when($this->search, fn ($q) => $q->where('code', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->orderBy('code')
            ->paginate(10);
    }

    public function getCategoriesProperty()
    {
        return Category::orderBy('sort_order')->orderBy('code')->get();
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">Item Search</h1>

    <x-card>
        <div class="flex items-center gap-3 mb-4">
            <input type="text" wire:model.live="search" placeholder="Search…" class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm" />
            <button wire:click="openCreate" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">+ Add</button>
        </div>

        <div class="flex gap-2 mb-4 flex-wrap">
            <button wire:click="$set('categoryFilter', null)" class="px-3 py-1 rounded-full text-sm {{ $categoryFilter === null ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700' }}">All</button>
            @foreach ($this->categories as $cat)
                <button wire:click="$set('categoryFilter', {{ $cat->id }})" class="px-3 py-1 rounded-full text-sm {{ $categoryFilter === $cat->id ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $cat->code }}</button>
            @endforeach
        </div>

        <x-table>
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Code</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Description</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Category</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Unit Price</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Active</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Type</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->items as $item)
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2">{{ $item->code }}</td>
                        <td class="px-3 py-2">{{ $item->description }}</td>
                        <td class="px-3 py-2">{{ $item->category->code ?? '' }}</td>
                        <td class="px-3 py-2">{{ number_format((float)$item->unit_price, 2) }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-xs {{ $item->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $item->active ? 'Yes' : 'No' }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $item->type ?? 'General' }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <button class="text-purple-600 hover:underline mr-2">Photos</button>
                            <button class="text-green-600 hover:underline mr-2">Modifiers</button>
                            <button wire:click="openEdit({{ $item->id }})" class="text-blue-600 hover:underline mr-2">Edit</button>
                            <button wire:click="confirmDelete({{ $item->id }})" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>{{ $this->items->total() }} records | Page {{ $this->items->currentPage() }} of {{ $this->items->lastPage() }}</span>
            <div class="flex gap-2">
                @if ($this->items->onFirstPage())
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Previous</button>
                @else
                    <button wire:click="previousPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</button>
                @endif
                @if ($this->items->hasMorePages())
                    <button wire:click="nextPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Next</button>
                @else
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Next</button>
                @endif
            </div>
        </div>
    </x-card>

    <x-modal :show="$showModal" title="{{ $editingId ? 'Edit Item' : 'Add Item' }}" close="$set('showModal', false)">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                <input type="text" wire:model="code" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select wire:model="category_id" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">-- Select Category --</option>
                    @foreach ($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->code }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" wire:model="description" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea wire:model="notes" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" rows="2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price</label>
                <input type="number" step="0.01" wire:model="unit_price" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('unit_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="active" /> Active
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="is_combo" /> Combo Item
                </label>
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button wire:click="$set('showModal', false)" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="save" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded">{{ $editingId ? 'Update' : 'Add' }}</button>
        </div>
    </x-modal>

    <x-modal :show="(bool) $deleteId" title="Delete Item" close="$set('deleteId', null)">
        <p class="text-sm text-gray-700 mb-4">Delete this item?</p>
        <div class="flex justify-end gap-2">
            <button wire:click="cancelDelete" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="delete" class="px-4 py-2 text-sm bg-red-600 text-white rounded">Delete</button>
        </div>
    </x-modal>
</div>