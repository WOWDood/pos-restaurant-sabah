<?php

use App\Models\RestaurantTable;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Table Search')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $name = '';
    public ?int $editingId = null;
    public bool $showModal = false;
    public ?int $deleteId = null;

    public function updatingSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['name', 'editingId']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $t = RestaurantTable::findOrFail($id);
        $this->editingId = $t->id;
        $this->name = $t->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:100|unique:restaurant_tables,name' . ($this->editingId ? ',' . $this->editingId : ''),
        ]);

        if ($this->editingId) {
            RestaurantTable::findOrFail($this->editingId)->update(['name' => $this->name]);
        } else {
            RestaurantTable::create(['name' => $this->name]);
        }

        $this->showModal = false;
        $this->reset(['name', 'editingId']);
    }

    public function confirmDelete(int $id): void { $this->deleteId = $id; }
    public function delete(): void
    {
        if ($this->deleteId) {
            $t = RestaurantTable::findOrFail($this->deleteId);
            if ($t->orders()->exists()) {
                $this->addError('delete', 'Cannot delete a table that has orders.');

                return;
            }
            $t->delete();
        }
        $this->deleteId = null;
    }
    public function cancelDelete(): void { $this->deleteId = null; }

    public function getTablesProperty()
    {
        return RestaurantTable::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">Table Search</h1>

    <x-card>
        <div class="flex items-center gap-3 mb-4">
            <input type="text" wire:model.live="search" placeholder="Search…" class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm" />
            <button wire:click="openCreate" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">+ Add</button>
        </div>

        <x-table>
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Description</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->tables as $table)
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2">{{ $table->name }}</td>
                        <td class="px-3 py-2">
                            <button wire:click="openEdit({{ $table->id }})" class="text-blue-600 hover:underline mr-2">Edit</button>
                            <button wire:click="confirmDelete({{ $table->id }})" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>{{ $this->tables->total() }} records | Page {{ $this->tables->currentPage() }} of {{ $this->tables->lastPage() }}</span>
            <div class="flex gap-2">
                @if ($this->tables->onFirstPage())
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Previous</button>
                @else
                    <button wire:click="previousPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</button>
                @endif
                @if ($this->tables->hasMorePages())
                    <button wire:click="nextPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Next</button>
                @else
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Next</button>
                @endif
            </div>
        </div>
    </x-card>

    <x-modal :show="$showModal" title="{{ $editingId ? 'Edit Table' : 'Add Table' }}" close="$set('showModal', false)">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" wire:model="name" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button wire:click="$set('showModal', false)" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="save" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded">{{ $editingId ? 'Update' : 'Add' }}</button>
        </div>
    </x-modal>

    <x-modal :show="(bool) $deleteId" title="Delete Table" close="$set('deleteId', null)">
        <p class="text-sm text-gray-700 mb-4">Delete this table?</p>
        @error('delete') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
        <div class="flex justify-end gap-2">
            <button wire:click="cancelDelete" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="delete" class="px-4 py-2 text-sm bg-red-600 text-white rounded">Delete</button>
        </div>
    </x-modal>
</div>