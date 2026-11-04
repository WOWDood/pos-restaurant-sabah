<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile')] class extends Component {
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset(['password', 'password_confirmation']);

        session()->flash('status', 'password-updated');
    }
}; ?>

<div class="space-y-6">
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Profile Information</h2>
        <div class="space-y-2 text-sm">
            <div class="flex gap-4">
                <span class="w-24 text-gray-500">Name</span>
                <span class="font-medium text-gray-900">{{ Auth::user()->name }}</span>
            </div>
            <div class="flex gap-4">
                <span class="w-24 text-gray-500">Code</span>
                <span class="font-medium text-gray-900">{{ Auth::user()->code ?? Auth::user()->email }}</span>
            </div>
            <div class="flex gap-4">
                <span class="w-24 text-gray-500">User Type</span>
                <span class="font-medium text-gray-900">{{ Auth::user()->user_type ?? 'U' }}</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Update Password</h2>
        <p class="text-sm text-gray-500 mb-4">Ensure your account is using a long, random password to stay secure.</p>

        @if (session('status') === 'password-updated')
            <div class="mb-4 text-sm text-green-600">Password updated.</div>
        @endif

        <form wire:submit="updatePassword" class="space-y-4 max-w-md">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" wire:model="password" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" wire:model="password_confirmation" class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <button type="submit" class="bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded">SAVE</button>
        </form>
    </div>
</div>