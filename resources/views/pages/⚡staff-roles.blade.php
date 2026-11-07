<?php

use App\Models\StaffRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Staff Roles')] class extends Component {
    public string $name = '';
    /** @var list<string> */
    public array $permissions = [];
    #[Locked]
    public ?int $editingId = null;
    public bool $showModal = false;

    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    public function openCreate(): void
    {
        $this->reset('name', 'permissions', 'editingId');
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $role = StaffRole::query()->findOrFail($id);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->permissions = $role->permissions;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset('name', 'permissions', 'editingId');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('staff_roles', 'name')->ignore($this->editingId)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys(StaffRole::PERMISSIONS))],
        ]);
        if (in_array(strtolower($data['name']), ['administrator', 'admin', 'staff'], true)) {
            $this->addError('name', 'Choose a name other than Administrator or Staff. These are built-in access levels.');
            return;
        }
        if ($this->editingId) {
            StaffRole::query()->findOrFail($this->editingId)->update($data);
        } else {
            StaffRole::create($data);
        }
        $this->closeModal();
        session()->flash('action_success', 'Staff role saved. Assigned staff will use these permissions on their next request.');
    }

    public function getRolesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return StaffRole::query()->withCount('users')->orderBy('name')->get();
    }
}; ?>

<section class="shop-settings-page pos-light-surface space-y-6">
    <x-page-heading title="Staff Roles" description="Define staff roles and manage access to POS functions." />
    <x-shop-settings-nav />
    <x-action-feedback />
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl"><h2 class="text-lg font-semibold">Custom Staff Roles</h2><p class="mt-1 text-gray-600">Create roles such as Cashier or Menu Manager, then assign them in Manage Users. POS access also includes viewing orders. Unselected permissions are blocked.</p></div>
            <button type="button" wire:click="openCreate" class="row-action row-action-primary">+ Add Role</button>
        </div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse ($this->roles as $role)
                <article wire:key="staff-role-{{ $role->id }}" class="min-w-0 rounded-xl border border-gray-200 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-words text-lg font-semibold">{{ $role->name }}</h3><p class="text-sm text-gray-600">{{ $role->users_count }} assigned staff</p></div><button type="button" wire:click="openEdit({{ $role->id }})" class="row-action" aria-label="Edit {{ $role->name }} role">Edit Role</button></div>
                    <ul class="mt-4 space-y-2 text-sm text-gray-700">
                        @forelse ($role->permissions as $permission)
                            <li>{{ \App\Models\StaffRole::PERMISSIONS[$permission] ?? $permission }}</li>
                        @empty
                            <li>Profile access only. No POS or setup access.</li>
                        @endforelse
                    </ul>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-gray-300 p-6 text-gray-600 lg:col-span-2">No custom roles yet. Add a role to tailor access for your team.</p>
            @endforelse
        </div>
    </x-card>
    <x-card><h2 class="text-lg font-semibold">Built-In Access Levels</h2><div class="mt-4 grid gap-5 sm:grid-cols-2"><div><h3 class="font-semibold text-indigo-800">Administrator</h3><p class="mt-1 text-gray-600">Full access, including shop settings, staff accounts, and staff roles.</p></div><div><h3 class="font-semibold">Staff</h3><p class="mt-1 text-gray-600">Existing staff keep full operational access. Assign a custom role to limit their access. Custom roles cannot grant administrator powers.</p></div></div></x-card>
    <x-modal :show="$showModal" title="{{ $editingId ? 'Edit Staff Role' : 'Add Staff Role' }}" close="closeModal" wide>
        <form wire:submit="save" class="space-y-5">
            <flux:input wire:model="name" label="Role Name" maxlength="80" required />
            <fieldset class="space-y-3"><legend class="mb-3 font-semibold">Allowed Actions</legend>
                @foreach (\App\Models\StaffRole::PERMISSIONS as $permission => $label)
                    <label wire:key="permission-{{ $permission }}" class="flex min-h-11 items-start gap-3 rounded-lg border border-gray-200 px-4 py-3"><input type="checkbox" wire:model="permissions" value="{{ $permission }}" class="mt-1 size-4 shrink-0 accent-indigo-700" /><span>{{ $label }}</span></label>
                @endforeach
            </fieldset>
            @error('permissions.*') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
            @error('permissions') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
            <p class="text-sm text-gray-600">No actions selected means profile access only. Shop settings and staff administration always require Administrator access.</p>
            <div class="row-actions justify-end"><button type="button" wire:click="closeModal" class="row-action row-action-neutral">Cancel</button><button type="submit" class="row-action row-action-primary" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save Role</span><span wire:loading wire:target="save" role="status">Saving…</span></button></div>
        </form>
    </x-modal>
</section>
