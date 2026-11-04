<?php

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manage Users')] class extends Component {
    use PasswordValidationRules, WithPagination;

    public string $search = '';
    public string $name = '';
    public string $email = '';
    public string $code = '';
    public string $userType = User::TYPE_STAFF;
    public string $password = '';
    public string $password_confirmation = '';
    #[Locked]
    public ?int $editingId = null;
    public bool $showModal = false;

    public function boot(): void
    {
        Gate::authorize('manage-users');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->code = $user->code ?? '';
        $this->userType = $user->isAdministrator() ? User::TYPE_ADMIN : User::TYPE_STAFF;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->showModal = false;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'code' => ['required', 'string', 'max:100', Rule::unique('users', 'code')->ignore($this->editingId)],
            'userType' => ['required', Rule::in([User::TYPE_ADMIN, User::TYPE_STAFF])],
        ];
        if (! $this->editingId) {
            $rules['password'] = $this->passwordRules();
        }
        $data = $this->validate($rules);

        $saved = DB::transaction(function () use ($data): bool {
            $actor = User::query()->lockForUpdate()->findOrFail(auth()->id());
            abort_unless($actor->isAdministrator(), 403);

            if ($this->editingId === (int) $actor->id && $data['userType'] !== User::TYPE_ADMIN) {
                $this->addError('userType', 'You cannot remove your own administrator access.');
                return false;
            }

            $attributes = ['name' => $data['name'], 'email' => $data['email'], 'code' => $data['code'] ?: null, 'user_type' => $data['userType']];
            if ($this->editingId) {
                $user = User::query()->lockForUpdate()->findOrFail($this->editingId);
                if ($user->email !== $data['email']) {
                    $attributes['email_verified_at'] = null;
                }
                $user->update($attributes);
            } else {
                $attributes['password'] = $data['password'];
                User::create($attributes);
            }

            return true;
        });

        if ($saved) {
            session()->flash('user_saved', 'User saved successfully.');
            $this->closeModal();
        }
    }

    public function getUsersProperty()
    {
        return User::query()
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")))
            ->orderBy('name')->orderBy('id')->paginate(10);
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'email', 'code', 'userType', 'password', 'password_confirmation', 'editingId']);
        $this->resetValidation();
    }
}; ?>

<div class="space-y-4">
    <div>
        <h1 class="text-xl font-bold text-gray-900">Manage Users</h1>
        <p class="mt-1 text-sm text-gray-600">Create staff accounts and manage administrator access.</p>
    </div>
    @if (session()->has('user_saved'))
        <div role="status" class="rounded bg-green-100 px-4 py-3 text-sm text-green-800">{{ session('user_saved') }}</div>
    @endif
    <x-card>
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="min-w-48 flex-1 text-sm font-medium text-gray-700">Search users
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, email or code" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
            </label>
            <button type="button" wire:click="openCreate" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white">+ Add user</button>
        </div>
        <x-table>
            <thead class="bg-gray-50"><tr>
                <th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2 text-left">Email</th><th class="px-3 py-2 text-left">Code</th><th class="px-3 py-2 text-left">Access</th><th class="px-3 py-2 text-left">Action</th>
            </tr></thead>
            <tbody>
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="border-t border-gray-100">
                        <td class="px-3 py-2">{{ $user->name }}</td><td class="px-3 py-2">{{ $user->email }}</td><td class="px-3 py-2">{{ $user->code ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $user->isAdministrator() ? 'Administrator' : 'Staff' }}</td>
                        <td class="px-3 py-2"><button type="button" wire:click="openEdit({{ $user->id }})" class="text-indigo-700 hover:underline" aria-label="Edit {{ $user->name }}">Edit</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        <div class="mt-4">{{ $this->users->links() }}</div>
    </x-card>
    <x-modal :show="$showModal" title="{{ $editingId ? 'Edit user' : 'Add user' }}" close="closeModal">
        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="name" label="Name" required />
            <flux:input wire:model="email" type="email" label="Email" required />
            <flux:input wire:model="code" label="Staff code" required />
            <flux:select wire:model="userType" label="Access">
                <flux:select.option value="staff">Staff</flux:select.option>
                <flux:select.option value="admin">Administrator</flux:select.option>
            </flux:select>
            @if (! $editingId)
                <flux:input wire:model="password" type="password" label="Temporary password" autocomplete="new-password" required />
                <flux:input wire:model="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
                <p class="text-sm text-gray-600">The user must verify their email before accessing the POS.</p>
            @else
                <p class="text-sm text-gray-600">Changing the email requires the user to verify the new address.</p>
            @endif
            <div class="flex justify-end gap-2"><flux:button type="button" wire:click="closeModal">Cancel</flux:button><flux:button type="submit" variant="primary">Save user</flux:button></div>
        </form>
    </x-modal>
</div>
