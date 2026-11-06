<?php

use App\Models\Category;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Category')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $code = '';
    public string $description = '';
    public int $sort_order = 0;
    public ?int $editingId = null;
    public bool $showModal = false;
    public ?int $deleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['code', 'description', 'sort_order', 'editingId']);
        $this->sort_order = 0;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->code = $category->code;
        $this->description = $category->description;
        $this->sort_order = $category->sort_order;
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'code' => 'required|string|max:50|unique:categories,code' . ($this->editingId ? ',' . $this->editingId : ''),
            'description' => 'required|string|max:255',
            'sort_order' => 'required|integer|unique:categories,sort_order' . ($this->editingId ? ',' . $this->editingId : ''),
        ];
        $this->validate($rules);

        if ($this->editingId) {
            Category::findOrFail($this->editingId)->update([
                'code' => $this->code,
                'description' => $this->description,
                'sort_order' => $this->sort_order,
            ]);
        } else {
            Category::create([
                'code' => $this->code,
                'description' => $this->description,
                'sort_order' => $this->sort_order,
            ]);
        }

        $this->showModal = false;
        session()->flash('action_success', 'Category saved.');
        $this->reset(['code', 'description', 'sort_order', 'editingId']);
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            $category = Category::findOrFail($this->deleteId);
            if ($category->items()->exists()) {
                $this->addError('delete', 'Cannot delete a category that still has items.');
                $this->deleteId = null;
                return;
            }
            $category->delete();
            session()->flash('action_success', 'Category deleted.');
        }
        $this->deleteId = null;
    }

    public function cancelDelete(): void
    {
        $this->deleteId = null;
    }

    public function getCategoriesProperty()
    {
        return Category::query()
            ->when($this->search, fn ($q) => $q->where('code', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%"))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(10);
    }
}; ?>

<div class="space-y-4">
    <x-action-feedback />
    <h1 class="text-xl font-bold text-gray-900">Category Search</h1>

    <x-card>
        <div class="flex items-center gap-3 mb-4">
            <input type="text" wire:model.live="search" placeholder="Search…" class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm" />
            <button wire:click="openCreate" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">+ Add</button>
        </div>

        <x-table>
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Code</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Description</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Sort Order</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->categories as $category)
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2">{{ $category->code }}</td>
                        <td class="px-3 py-2">{{ $category->description }}</td>
                        <td class="px-3 py-2">{{ $category->sort_order }}</td>
                        <td class="px-3 py-2">
                            <button wire:click="openEdit({{ $category->id }})" class="text-blue-600 hover:underline mr-2">Edit</button>
                            <button wire:click="confirmDelete({{ $category->id }})" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>{{ $this->categories->total() }} records | Page {{ $this->categories->currentPage() }} of {{ $this->categories->lastPage() }}</span>
            <div class="flex gap-2">
                @if ($this->categories->onFirstPage())
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Previous</button>
                @else
                    <button wire:click="previousPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</button>
                @endif
                @if ($this->categories->hasMorePages())
                    <button wire:click="nextPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Next</button>
                @else
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Next</button>
                @endif
            </div>
        </div>
    </x-card>

    <x-modal :show="$showModal" title="{{ $editingId ? 'Edit Category' : 'Add Category' }}" close="$set('showModal', false)">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                <input type="text" wire:model="code" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" wire:model="description" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                <input type="number" wire:model="sort_order" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('sort_order') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button wire:click="$set('showModal', false)" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="save" class="pending-action px-4 py-2 text-sm bg-indigo-600 text-white rounded" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">{{ $editingId ? 'Update' : 'Add' }}</span><span wire:loading wire:target="save" role="status">Saving…</span></button>
        </div>
    </x-modal>

    <x-modal :show="(bool) $deleteId" title="Delete Category" close="$set('deleteId', null)">
        <p class="text-sm text-gray-700 mb-4">Delete this category?</p>
        @error('delete') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
        <div class="flex justify-end gap-2">
            <button wire:click="cancelDelete" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="delete" class="pending-action px-4 py-2 text-sm bg-red-600 text-white rounded" wire:loading.attr="disabled" wire:target="delete"><span wire:loading.remove wire:target="delete">Delete</span><span wire:loading wire:target="delete" role="status">Deleting…</span></button>
        </div>
    </x-modal>
</div>
