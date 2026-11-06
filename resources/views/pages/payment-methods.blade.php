<?php

use App\Models\PaymentMethod;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payment Methods')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $description = '';
    public int $sort_order = 0;
    public bool $active = true;
    public bool $ref_required = false;
    public ?int $editingId = null;
    public bool $showModal = false;
    public ?int $deleteId = null;

    public function updatingSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['description', 'sort_order', 'active', 'ref_required', 'editingId']);
        $this->active = true;
        $this->sort_order = 0;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $pm = PaymentMethod::findOrFail($id);
        $this->editingId = $pm->id;
        $this->description = $pm->description;
        $this->sort_order = $pm->sort_order;
        $this->active = (bool) $pm->active;
        $this->ref_required = (bool) $pm->ref_required;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'description' => 'required|string|max:100',
            'sort_order' => 'required|integer|unique:payment_methods,sort_order' . ($this->editingId ? ',' . $this->editingId : ''),
        ]);

        $data = [
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'active' => $this->active,
            'ref_required' => $this->ref_required,
        ];

        if ($this->editingId) {
            PaymentMethod::findOrFail($this->editingId)->update($data);
        } else {
            PaymentMethod::create($data);
        }

        $this->showModal = false;
        $this->reset(['description', 'sort_order', 'active', 'ref_required', 'editingId']);
        $this->active = true;
    }

    public function confirmDelete(int $id): void { $this->deleteId = $id; }
    public function delete(): void
    {
        if ($this->deleteId) {
            $pm = PaymentMethod::findOrFail($this->deleteId);
            if ($pm->payments()->exists()) {
                $this->addError('delete', 'Cannot delete a payment method that has payments.');

                return;
            }
            $pm->delete();
        }
        $this->deleteId = null;
    }
    public function cancelDelete(): void { $this->deleteId = null; }

    public function getMethodsProperty()
    {
        return PaymentMethod::query()
            ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%"))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(10);
    }
}; ?>

<div class="space-y-4">
    <h1 class="text-xl font-bold text-gray-900">Payment Method Search</h1>

    <x-card>
        <div class="flex items-center gap-3 mb-4">
            <input type="text" wire:model.live="search" placeholder="Search…" class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm" />
            <button wire:click="openCreate" class="bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded">+ Add</button>
        </div>

        <x-table>
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">ID</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Description</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Sort Order</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Active</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Ref Required</th>
                    <th class="text-left px-3 py-2 font-medium text-gray-600">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->methods as $pm)
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2">{{ $pm->id }}</td>
                        <td class="px-3 py-2">{{ $pm->description }}</td>
                        <td class="px-3 py-2">{{ $pm->sort_order }}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-sm {{ $pm->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $pm->active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-0.5 rounded text-sm {{ $pm->ref_required ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">{{ $pm->ref_required ? 'Yes' : 'No' }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <button wire:click="openEdit({{ $pm->id }})" class="text-blue-600 hover:underline mr-2">Edit</button>
                            <button wire:click="confirmDelete({{ $pm->id }})" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>

        <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>{{ $this->methods->total() }} records | Page {{ $this->methods->currentPage() }} of {{ $this->methods->lastPage() }}</span>
            <div class="flex gap-2">
                @if ($this->methods->onFirstPage())
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Previous</button>
                @else
                    <button wire:click="previousPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</button>
                @endif
                @if ($this->methods->hasMorePages())
                    <button wire:click="nextPage" class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Next</button>
                @else
                    <button class="px-3 py-1 border border-gray-200 rounded text-gray-400" disabled>Next</button>
                @endif
            </div>
        </div>
    </x-card>

    <x-modal :show="$showModal" title="{{ $editingId ? 'Edit Payment Method' : 'Add Payment Method' }}" close="$set('showModal', false)">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <input type="text" wire:model="description" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                <input type="number" wire:model="sort_order" class="w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="active" /> Active
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="ref_required" /> Reference Required
                </label>
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
            <button wire:click="$set('showModal', false)" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="save" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded">{{ $editingId ? 'Update' : 'Add' }}</button>
        </div>
    </x-modal>

    <x-modal :show="(bool) $deleteId" title="Delete Payment Method" close="$set('deleteId', null)">
        <p class="text-sm text-gray-700 mb-4">Delete this payment method?</p>
        @error('delete') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
        <div class="flex justify-end gap-2">
            <button wire:click="cancelDelete" class="px-4 py-2 text-sm border border-gray-300 rounded">Cancel</button>
            <button wire:click="delete" class="px-4 py-2 text-sm bg-red-600 text-white rounded">Delete</button>
        </div>
    </x-modal>
</div>
