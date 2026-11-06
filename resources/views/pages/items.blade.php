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

new #[Title('Item')] class extends Component {
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
    public ?int $editingGroupId = null;
    public bool $showGroupForm = false;
    public ?int $addingOptionForGroup = null;
    public ?int $newOptionGroupId = null;
    public string $newOptionEn = '';
    public string $newOptionZh = '';
    public float $newOptionPrice = 0;
    public int $newOptionSort = 0;
    public bool $newOptionBase = false;
    public ?int $editingOptionId = null;

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
            $item = Item::findOrFail($this->deleteId);
            if ($item->orderItems()->exists()) {
                $this->addError('delete', 'Cannot delete an item that is used in orders.');

                return;
            }
            $item->delete();
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

    public function removePhotoFile(int $index): void
    {
        unset($this->photoFiles[$index]);
        $this->photoFiles = array_values($this->photoFiles);
    }

    public function uploadPhotos(): void
    {
        $this->validate(['photoFiles.*' => 'image|max:5120']);
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

    public function saveGroup(): void
    {
        $this->validate([
            'newGroupName' => 'required|string|max:100',
            'newGroupMin' => 'required|integer|min:0',
            'newGroupMax' => 'required|integer|min:1',
            'newGroupSort' => 'required|integer|unique:modifier_groups,sort_order,' . ($this->editingGroupId ?: 'NULL') . ',id,item_id,' . $this->modifierItemId,
        ]);
        $data = [
            'item_id' => $this->modifierItemId,
            'name' => $this->newGroupName,
            'min_select' => $this->newGroupMin,
            'max_select' => $this->newGroupMax,
            'sort_order' => $this->newGroupSort,
        ];
        if ($this->editingGroupId) {
            ModifierGroup::findOrFail($this->editingGroupId)->update($data);
        } else {
            ModifierGroup::create($data);
        }
        $this->reset(['newGroupName', 'newGroupMin', 'newGroupMax', 'newGroupSort', 'editingGroupId', 'showGroupForm']);
        $this->newGroupMin = 0;
        $this->newGroupMax = 1;
    }

    public function openEditGroup(int $id): void
    {
        $g = ModifierGroup::findOrFail($id);
        $this->editingGroupId = $g->id;
        $this->newGroupName = $g->name;
        $this->newGroupMin = $g->min_select;
        $this->newGroupMax = $g->max_select;
        $this->newGroupSort = $g->sort_order;
        $this->showGroupForm = true;
    }

    public function cancelEditGroup(): void
    {
        $this->reset(['newGroupName', 'newGroupMin', 'newGroupMax', 'newGroupSort', 'editingGroupId']);
        $this->newGroupMin = 0;
        $this->newGroupMax = 1;
    }

    public function deleteGroup(int $id): void
    {
        ModifierGroup::findOrFail($id)->delete();
    }

    public function saveOption(): void
    {
        $this->validate([
            'newOptionGroupId' => 'required|exists:modifier_groups,id',
            'newOptionEn' => 'required|string|max:255',
            'newOptionSort' => 'required|integer|unique:modifier_options,sort_order,' . ($this->editingOptionId ?: 'NULL') . ',id,modifier_group_id,' . $this->newOptionGroupId,
        ]);
        $data = [
            'modifier_group_id' => $this->newOptionGroupId,
            'name_en' => $this->newOptionEn,
            'name_zh' => $this->newOptionZh ?: null,
            'price_adjustment' => $this->newOptionPrice,
            'is_base' => $this->newOptionBase,
            'sort_order' => $this->newOptionSort,
        ];
        if ($this->editingOptionId) {
            ModifierOption::findOrFail($this->editingOptionId)->update($data);
        } else {
            ModifierOption::create($data);
        }
        $this->reset(['newOptionEn', 'newOptionZh', 'newOptionPrice', 'newOptionSort', 'newOptionBase', 'editingOptionId']);
        $this->newOptionPrice = 0;
        $this->addingOptionForGroup = null;
    }

    public function openEditOption(int $id): void
    {
        $o = ModifierOption::findOrFail($id);
        $this->editingOptionId = $o->id;
        $this->newOptionGroupId = $o->modifier_group_id;
        $this->newOptionEn = $o->name_en;
        $this->newOptionZh = $o->name_zh ?? '';
        $this->newOptionPrice = (float) $o->price_adjustment;
        $this->newOptionSort = $o->sort_order;
        $this->newOptionBase = (bool) $o->is_base;
    }

    public function cancelEditOption(): void
    {
        $this->reset(['newOptionEn', 'newOptionZh', 'newOptionPrice', 'newOptionSort', 'newOptionBase', 'editingOptionId']);
        $this->newOptionPrice = 0;
    }

    public function saveOptionForGroup(int $groupId): void
    {
        $this->newOptionGroupId = $groupId;
        $this->saveOption();
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
                            <span class="px-2 py-0.5 rounded text-sm {{ $item->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $item->active ? 'Yes' : 'No' }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-sm bg-gray-100 text-gray-600">{{ $item->type ?? 'General' }}</span>
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
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select wire:model="category_id" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">-- Select Category --</option>
                    @foreach ($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->code }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" wire:model="description" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea wire:model="notes" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" rows="2"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price</label>
                <input type="number" step="0.01" wire:model="unit_price" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('unit_price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
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
        @error('delete') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
        <div class="flex justify-end gap-2">
            <button wire:click="cancelDelete" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="delete" class="px-4 py-2 text-sm bg-red-600 text-white rounded">Delete</button>
        </div>
    </x-modal>

    {{-- Item Photos Modal --}}
    <x-modal :show="$showPhotosModal" title="Item Photos" close="$set('showPhotosModal', false)">
        @if ($this->photoItem)
            <div class="space-y-3">
                <form wire:submit.prevent="uploadPhotos" class="space-y-3">
                    <label class="block border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-indigo-400">
                        <span class="text-sm text-gray-600">Drop photos here or</span>
                        <span class="ml-1 text-sm font-medium text-indigo-600">Choose photos</span>
                        <input type="file" wire:model="photoFiles" multiple accept="image/*" class="hidden" />
                    </label>
                    <p class="text-sm text-gray-400">Allowed: JPG, PNG, WEBP. Max 5 MB per image.</p>
                    <div wire:loading wire:target="photoFiles" class="text-sm text-gray-500">Preparing...</div>
                    @error('photoFiles') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('photoFiles.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    @if (count($photoFiles))
                        <ul class="space-y-1">
                            @foreach ($photoFiles as $i => $f)
                                <li class="flex items-center justify-between text-sm bg-gray-50 rounded px-2 py-1">
                                    <span class="truncate">{{ $f->getClientOriginalName() }}</span>
                                    <button type="button" wire:click="removePhotoFile({{ $i }})" class="text-gray-400 hover:text-red-600">&times;</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded disabled:opacity-50" @disabled(!count($photoFiles))>
                        Upload {{ count($photoFiles) }} photo{{ count($photoFiles) === 1 ? '' : 's' }}
                    </button>
                </form>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($this->photoItem->photos as $photo)
                        <div class="border border-gray-200 rounded p-2">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($photo->path) }}" class="w-full h-24 object-cover rounded mb-1" alt="" />
                            <p class="text-sm text-gray-500 truncate mb-1">{{ basename($photo->path) }}</p>
                            <div class="flex items-center justify-between">
                                @if ($photo->is_primary)
                                    <span class="text-sm bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-medium">Primary</span>
                                @else
                                    <button wire:click="setPrimaryPhoto({{ $photo->id }})" class="text-sm text-gray-600 hover:underline">Set Primary</button>
                                @endif
                                <button wire:click="deletePhoto({{ $photo->id }})" class="text-sm text-red-600 hover:underline">Delete</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-modal>

    <x-modal :show="$showModifiersModal" title="Modifier Groups" close="$set('showModifiersModal', false)" wide>
        @if ($this->modifierItem)
            <div class="space-y-4">
                @if (! $showGroupForm)
                    <button wire:click="$set('showGroupForm', true)" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded">+ Add Group</button>
                @else
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h3 class="text-sm font-semibold mb-2">{{ $editingGroupId ? 'Edit Group' : 'Add Group' }}</h3>
                        <div class="space-y-2">
                            <div>
                                <label class="block text-sm text-gray-500 mb-1">Group name</label>
                                <input type="text" wire:model="newGroupName" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-sm text-gray-500 mb-1">Minimum choices</label>
                                    <input type="number" wire:model="newGroupMin" min="0" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-500 mb-1">Maximum choices</label>
                                    <input type="number" wire:model="newGroupMax" min="1" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-500 mb-1">Sort order</label>
                                    <input type="number" wire:model="newGroupSort" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                                </div>
                            </div>
                            <p class="text-sm text-gray-400">Set both to 1 for a required single choice.</p>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button wire:click="saveGroup" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded">{{ $editingGroupId ? 'Update Group' : 'Add Group' }}</button>
                            <button wire:click="cancelEditGroup" class="px-3 py-1 text-sm border border-gray-300 rounded">Cancel</button>
                        </div>
                        @error('newGroupName') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif

                @foreach ($this->modifierItem->modifierGroups->sortBy('sort_order') as $group)
                    <div class="border border-gray-200 rounded-lg p-4">
                        @if ($editingGroupId === $group->id)
                            <div class="space-y-2">
                                <label class="block text-sm text-gray-500 mb-1">Group name</label>
                                <input type="text" wire:model="newGroupName" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Minimum choices</label>
                                        <input type="number" wire:model="newGroupMin" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Maximum choices</label>
                                        <input type="number" wire:model="newGroupMax" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Sort order</label>
                                        <input type="number" wire:model="newGroupSort" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-2">
                                    <button wire:click="saveGroup" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded">Update Group</button>
                                    <button wire:click="cancelEditGroup" class="px-3 py-1 text-sm border border-gray-300 rounded">Cancel</button>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-sm">{{ $group->name }}</span>
                                    <span class="text-sm bg-red-100 text-red-700 px-2 py-0.5 rounded">
                                        Required: choose {{ $group->min_select === $group->max_select ? $group->max_select : $group->min_select . '-' . $group->max_select }}
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <button wire:click="openEditGroup({{ $group->id }})" class="text-sm text-blue-600 hover:underline">Edit</button>
                                    <button wire:click="deleteGroup({{ $group->id }})" class="text-sm text-red-600 hover:underline">Delete</button>
                                </div>
                            </div>
                        @endif

                        <div class="space-y-1 mb-3">
                            @foreach ($group->options->sortBy('sort_order') as $opt)
                                @if ($editingOptionId === $opt->id)
                                    <div class="border-t border-gray-100 pt-2 space-y-2">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-sm text-gray-500 mb-1">Name (EN)</label>
                                                <input type="text" wire:model="newOptionEn" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-sm text-gray-500 mb-1">Name (ZH)</label>
                                                <input type="text" wire:model="newOptionZh" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-sm text-gray-500 mb-1">Price adjustment (RM)</label>
                                                <input type="number" step="0.01" wire:model="newOptionPrice" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                            </div>
                                            <div>
                                                <label class="block text-sm text-gray-500 mb-1">Sort order</label>
                                                <input type="number" wire:model="newOptionSort" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                            </div>
                                        </div>
                                        <label class="flex items-center gap-1 text-sm">
                                            <input type="checkbox" wire:model="newOptionBase" /> Base option
                                        </label>
                                        <div class="flex gap-2">
                                            <button wire:click="saveOption" class="px-3 py-1 text-sm bg-green-600 text-white rounded">Update Option</button>
                                            <button wire:click="cancelEditOption" class="px-3 py-1 text-sm border border-gray-300 rounded">Cancel</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-1">
                                        <div>
                                            <span>{{ $opt->name_en }}</span>
                                            @if ($opt->name_zh)
                                                <span class="text-gray-400"> / {{ $opt->name_zh }}</span>
                                            @endif
                                            <span class="text-gray-500 ml-2">+RM{{ number_format((float)$opt->price_adjustment, 2) }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="openEditOption({{ $opt->id }})" class="text-sm text-blue-600 hover:underline">Edit</button>
                                            <button wire:click="deleteOption({{ $opt->id }})" class="text-sm text-red-600 hover:underline">Delete</button>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if ($addingOptionForGroup === $group->id)
                            <div class="border-t border-gray-200 pt-3 space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Name (EN)</label>
                                        <input type="text" wire:model="newOptionEn" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Name (ZH)</label>
                                        <input type="text" wire:model="newOptionZh" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Price adjustment (RM)</label>
                                        <input type="number" step="0.01" wire:model="newOptionPrice" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm text-gray-500 mb-1">Sort order</label>
                                        <input type="number" wire:model="newOptionSort" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" />
                                    </div>
                                </div>
                                <label class="flex items-center gap-1 text-sm">
                                    <input type="checkbox" wire:model="newOptionBase" /> Base option
                                </label>
                                <div class="flex gap-2">
                                    <button wire:click="saveOptionForGroup({{ $group->id }})" class="px-3 py-1 text-sm bg-green-600 text-white rounded">Add Option</button>
                                    <button wire:click="$set('addingOptionForGroup', null)" class="px-3 py-1 text-sm border border-gray-300 rounded">Cancel</button>
                                </div>
                            </div>
                        @else
                            <button wire:click="$set('addingOptionForGroup', {{ $group->id }})" class="text-sm text-green-600 hover:underline">+ Add Option</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
</div>
