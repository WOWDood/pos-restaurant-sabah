<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Item Search')] class extends Component {
    use WithPagination;
    use WithFileUploads;

    public string $search = '';
    public ?int $categoryFilter = null;
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

    // Photos
    public ?int $photoItemId = null;
    public bool $showPhotosModal = false;
    public array $photoFiles = [];
    // Modifiers
    public ?int $modifierItemId = null;
    public bool $showModifiersModal = false;
    public string $newGroupName = '';
    public int $newGroupMin = 0;
    public int $newGroupMax = 1;
    public int $newGroupSort = 0;
    public ?int $newOptionGroupId = null;
    public string $newOptionEn = '';
    public string $newOptionZh = '';
    public float $newOptionPrice = 0;
    public int $newOptionSort = 0;
    public bool $newOptionBase = false;

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

    // Photos
    public function openPhotos(int $id): void
    {
        $this->photoItemId = $id;
        $this->photoFiles = [];
        $this->showPhotosModal = true;
    }

    public function uploadPhotos(): void
    {
        $this->validate(['photoFiles.*' => 'image|max:2048']);
        $item = Item::findOrFail($this->photoItemId);
        $hasPrimary = $item->photos()->where('is_primary', true)->exists();
        foreach ($this->photoFiles as $file) {
            $path = $file->store('item-photos', 'public');
            $item->photos()->create([
                'path' => $path,
                'is_primary' => ! $hasPrimary,
            ]);
            $hasPrimary = true;
        }
        $this->photoFiles = [];
    }

    public function setPrimaryPhoto(int $photoId): void
    {
        $photo = ItemPhoto::findOrFail($photoId);
        ItemPhoto::where('item_id', $photo->item_id)->update(['is_primary' => false]);
        $photo->update(['is_primary' => true]);
    }

    public function deletePhoto(int $photoId): void
    {
        $photo = ItemPhoto::findOrFail($photoId);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->path);
        $photo->delete();
    }

    // Modifiers
    public function openModifiers(int $id): void
    {
        $this->modifierItemId = $id;
        $this->showModifiersModal = true;
    }

    public function addGroup(): void
    {
        $this->validate([
            'newGroupName' => 'required|string|max:100',
            'newGroupMin' => 'required|integer|min:0',
            'newGroupMax' => 'required|integer|min:1',
        ]);
        ModifierGroup::create([
            'item_id' => $this->modifierItemId,
            'name' => $this->newGroupName,
            'min_select' => $this->newGroupMin,
            'max_select' => $this->newGroupMax,
            'sort_order' => $this->newGroupSort,
        ]);
        $this->reset(['newGroupName', 'newGroupMin', 'newGroupMax', 'newGroupSort']);
        $this->newGroupMin = 0;
        $this->newGroupMax = 1;
    }

    public function deleteGroup(int $id): void
    {
        ModifierGroup::findOrFail($id)->delete();
    }

    public function addOption(): void
    {
        $this->validate([
            'newOptionGroupId' => 'required|exists:modifier_groups,id',
            'newOptionEn' => 'required|string|max:255',
        ]);
        ModifierOption::create([
            'modifier_group_id' => $this->newOptionGroupId,
            'name_en' => $this->newOptionEn,
            'name_zh' => $this->newOptionZh ?: null,
            'price_adjustment' => $this->newOptionPrice,
            'is_base' => $this->newOptionBase,
            'sort_order' => $this->newOptionSort,
        ]);
        $this->reset(['newOptionEn', 'newOptionZh', 'newOptionPrice', 'newOptionSort', 'newOptionBase']);
        $this->newOptionPrice = 0;
    }

    public function deleteOption(int $id): void
    {
        ModifierOption::findOrFail($id)->delete();
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

    public function getPhotoItemProperty()
    {
        return $this->photoItemId ? Item::with('photos')->find($this->photoItemId) : null;
    }

    public function getModifierItemProperty()
    {
        return $this->modifierItemId ? Item::with('modifierGroups.options')->find($this->modifierItemId) : null;
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
                            <button wire:click="openPhotos({{ $item->id }})" class="text-purple-600 hover:underline mr-2">Photos</button>
                            <button wire:click="openModifiers({{ $item->id }})" class="text-green-600 hover:underline mr-2">Modifiers</button>
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

    {{-- Add/Edit Item Modal --}}
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

    {{-- Delete Item Modal --}}
    <x-modal :show="(bool) $deleteId" title="Delete Item" close="$set('deleteId', null)">
        <p class="text-sm text-gray-700 mb-4">Delete this item?</p>
        <div class="flex justify-end gap-2">
            <button wire:click="cancelDelete" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="delete" class="px-4 py-2 text-sm bg-red-600 text-white rounded">Delete</button>
        </div>
    </x-modal>

    {{-- Item Photos Modal --}}
    <x-modal :show="$showPhotosModal" title="Item Photos" close="$set('showPhotosModal', false)">
        @if ($this->photoItem)
            <div class="space-y-3">
                <form wire:submit.prevent="uploadPhotos" class="space-y-2">
                    <input type="file" wire:model="photoFiles" multiple accept="image/*" class="text-sm" />
                    @error('photoFiles.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded">Upload</button>
                </form>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($this->photoItem->photos as $photo)
                        <div class="border border-gray-200 rounded p-2 text-center">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->path) }}" class="w-full h-24 object-cover rounded mb-1" alt="" />
                            @if ($photo->is_primary)
                                <span class="text-xs text-blue-600 font-medium">Primary</span>
                            @else
                                <button wire:click="setPrimaryPhoto({{ $photo->id }})" class="text-xs text-gray-500 hover:underline">Set primary</button>
                            @endif
                            <div>
                                <button wire:click="deletePhoto({{ $photo->id }})" class="text-xs text-red-600 hover:underline">Delete</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-modal>

    {{-- Modifier Groups Modal --}}
    <x-modal :show="$showModifiersModal" title="Modifier Groups" close="$set('showModifiersModal', false)">
        @if ($this->modifierItem)
            <div class="space-y-4">
                <div class="border border-gray-200 rounded p-3">
                    <h3 class="text-sm font-semibold mb-2">Add Group</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" wire:model="newGroupName" placeholder="Group name" class="col-span-2 rounded border border-gray-300 px-2 py-1 text-sm" />
                        <input type="number" wire:model="newGroupMin" placeholder="Min" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                        <input type="number" wire:model="newGroupMax" placeholder="Max" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                        <input type="number" wire:model="newGroupSort" placeholder="Sort" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                    </div>
                    <button wire:click="addGroup" class="mt-2 px-3 py-1 text-sm bg-indigo-600 text-white rounded">+ Add Group</button>
                    @error('newGroupName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                @foreach ($this->modifierItem->modifierGroups as $group)
                    <div class="border border-gray-200 rounded p-3">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="font-medium text-sm">{{ $group->name }}</span>
                                <span class="text-xs text-red-600 ml-2">Required - Pick {{ $group->max_select }}</span>
                            </div>
                            <button wire:click="deleteGroup({{ $group->id }})" class="text-xs text-red-600 hover:underline">Delete</button>
                        </div>
                        <ul class="text-sm text-gray-600 mb-2">
                            @foreach ($group->options as $opt)
                                <li class="flex items-center justify-between py-0.5">
                                    <span>{{ $opt->name_en }}@if($opt->name_zh) / {{ $opt->name_zh }}@endif</span>
                                    <button wire:click="deleteOption({{ $opt->id }})" class="text-xs text-red-600 hover:underline">Delete</button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            <select wire:model="newOptionGroupId" class="col-span-3 rounded border border-gray-300 px-2 py-1 text-sm">
                                <option value="">-- Select group --</option>
                                @foreach ($this->modifierItem->modifierGroups as $g)
                                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="newOptionEn" placeholder="Name (EN)" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                            <input type="text" wire:model="newOptionZh" placeholder="Name (ZH)" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                            <input type="number" step="0.01" wire:model="newOptionPrice" placeholder="Price adj" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                            <input type="number" wire:model="newOptionSort" placeholder="Sort" class="rounded border border-gray-300 px-2 py-1 text-sm" />
                            <label class="flex items-center gap-1 text-xs">
                                <input type="checkbox" wire:model="newOptionBase" /> Base
                            </label>
                        </div>
                        <button wire:click="addOption" class="mt-2 px-3 py-1 text-sm bg-green-600 text-white rounded">+ Add Option</button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
</div>