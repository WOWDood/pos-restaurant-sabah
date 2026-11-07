<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $profile_password = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $this->name = trim($this->name);
        $this->email = trim($this->email);
        $rules = $this->profileRules($user->id);

        if ($this->email !== $user->email) {
            $rules['profile_password'] = $this->currentPasswordRules();
        }

        try {
            $validated = $this->validate($rules, attributes: ['profile_password' => 'current password']);
        } finally {
            $this->reset('profile_password');
        }

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();
        session()->flash('action_success', $emailChanged
            ? 'Profile saved. Verify your new email address to continue using the POS.'
            : 'Profile saved.');

        $this->redirect(route('profile'), navigate: true);
    }

    public function resetProfileInformation(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->reset('profile_password');
        $this->resetValidation(['name', 'email', 'profile_password']);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $key = 'profile-verification:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('verification', 'Please wait a minute before requesting another verification email.');

            return;
        }

        $user->sendEmailVerificationNotification();
        RateLimiter::hit($key, 60);
        $this->resetValidation('verification');
        session()->flash('action_success', 'Verification email sent. Check your inbox and spam folder.');
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $exception) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $exception;
        }

        Auth::user()->update(['password' => $validated['password']]);
        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('action_success', 'Password updated. Use your new password next time you sign in.');
    }
}; ?>

<section class="profile-page space-y-6" aria-labelledby="profile-heading">
    <header>
        <h1 id="profile-heading" class="text-2xl font-bold tracking-tight text-gray-900">My profile</h1>
        <p class="mt-1 text-gray-600">Your details, sign-in preferences, and account security.</p>
    </header>

    <x-action-feedback />

    <x-card>
        <div class="grid grid-cols-[4rem_minmax(0,1fr)] items-center gap-x-4 gap-y-2 sm:flex sm:gap-4">
            <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-xl font-bold text-indigo-800" aria-hidden="true">{{ Auth::user()->initials() }}</span>
            <div class="min-w-0 flex-1">
                <h2 class="break-words text-xl font-semibold text-gray-900">{{ Auth::user()->name }}</h2>
                <p class="break-all text-gray-600">{{ Auth::user()->email }}</p>
            </div>
            <span class="col-start-2 justify-self-start rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-800 sm:shrink-0">{{ Auth::user()->isAdministrator() ? 'Administrator' : 'Staff' }}</span>
        </div>
        <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-3">
            <div><dt class="text-gray-500">Staff code</dt><dd class="mt-1 break-words font-medium">{{ Auth::user()->code ?? 'Not assigned' }}</dd></div>
            <div><dt class="text-gray-500">Member since</dt><dd class="mt-1 font-medium">{{ Auth::user()->created_at?->setTimezone('Asia/Kuala_Lumpur')->format('d M Y') ?? 'Not available' }}</dd></div>
            <div><dt class="text-gray-500">Email status</dt><dd class="mt-1 font-medium {{ Auth::user()->hasVerifiedEmail() ? 'text-green-700' : 'text-amber-800' }}">{{ Auth::user()->hasVerifiedEmail() ? 'Verified' : 'Verification needed' }}</dd></div>
        </dl>
    </x-card>

    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <x-card class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900">Personal details</h2>
            <p class="mt-1 text-gray-600">Keep your name and sign-in email up to date.</p>
            <form wire:submit="updateProfileInformation" class="mt-6 space-y-5">
                <flux:input wire:model="name" label="Full name" autocomplete="name" maxlength="255" required />
                <flux:input wire:model="email" label="Email address" type="email" autocomplete="email" maxlength="255" required />
                <flux:input wire:model="profile_password" label="Current password for email changes" type="password" autocomplete="current-password" description="Only required when you change your email address. You will need to verify the new address before returning to the POS." viewable />
                <p class="text-sm text-gray-500">Ask an administrator to change your staff code or access level.</p>
                <div class="row-actions">
                    <button type="submit" class="row-action row-action-primary pending-action" wire:loading.attr="disabled" wire:target="updateProfileInformation">
                        <span wire:loading.remove wire:target="updateProfileInformation">Save details</span><span wire:loading wire:target="updateProfileInformation" role="status">Saving…</span>
                    </button>
                    <button type="button" wire:click="resetProfileInformation" class="row-action row-action-neutral" wire:loading.attr="disabled" wire:target="updateProfileInformation,resetProfileInformation">Cancel changes</button>
                </div>
            </form>
        </x-card>

        <x-card class="min-w-0">
            <h2 class="text-lg font-semibold text-gray-900">Change password</h2>
            <p class="mt-1 text-gray-600">Confirm your current password to choose a new one.</p>
            <form wire:submit="updatePassword" class="mt-6 space-y-5">
                <flux:input wire:model="current_password" label="Current password" type="password" autocomplete="current-password" required viewable />
                <flux:input wire:model="password" label="New password" type="password" autocomplete="new-password" description="Choose 12 or more characters with uppercase and lowercase letters, a number, and a symbol." required viewable />
                <flux:input wire:model="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required viewable />
                <button type="submit" class="row-action row-action-primary pending-action" wire:loading.attr="disabled" wire:target="updatePassword">
                    <span wire:loading.remove wire:target="updatePassword">Update password</span><span wire:loading wire:target="updatePassword" role="status">Updating…</span>
                </button>
            </form>
        </x-card>
    </div>

    <x-card>
        <h2 class="text-lg font-semibold text-gray-900">Account security</h2>
        <p class="mt-1 text-gray-600">Extra protection for your restaurant workspace.</p>
        <div class="mt-5 divide-y divide-gray-100">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-5">
                <div class="min-w-0 flex-1 basis-64">
                    <h3 class="font-semibold">Email verification</h3>
                    <p class="mt-1 text-gray-600">{{ Auth::user()->hasVerifiedEmail() ? 'Your sign-in email has been verified.' : 'Verify your saved email address to access the POS, orders, and dashboard.' }}</p>
                </div>
                @if (! Auth::user()->hasVerifiedEmail())
                    <button type="button" wire:click="resendVerificationNotification" class="row-action" wire:loading.attr="disabled" wire:target="resendVerificationNotification">
                        <span wire:loading.remove wire:target="resendVerificationNotification">Send verification email</span><span wire:loading wire:target="resendVerificationNotification" role="status">Sending…</span>
                    </button>
                @else
                    <span class="rounded-full bg-green-50 px-3 py-1 font-medium text-green-800">Verified</span>
                @endif
            </div>
            @error('verification') <p role="alert" class="pb-4 text-red-700">{{ $message }}</p> @enderror
            <div class="flex flex-wrap items-center justify-between gap-4 py-5">
                <div class="min-w-0 flex-1 basis-64">
                    <h3 class="font-semibold">Two-factor authentication &amp; passkeys</h3>
                    <p class="mt-1 text-gray-600">{{ Auth::user()->hasEnabledTwoFactorAuthentication() ? 'Two-factor authentication is enabled.' : 'Add an authenticator app or a passkey for more secure sign-in.' }}</p>
                </div>
                <a href="{{ route('security.edit') }}" class="row-action" wire:navigate>Manage sign-in security</a>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 pt-5">
                <div class="min-w-0 flex-1 basis-64">
                    <h3 class="font-semibold">Using a shared device?</h3>
                    <p class="mt-1 text-gray-600">Sign out when your shift ends to keep your account private.</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="row-action row-action-neutral">Sign out of this device</button>
                </form>
            </div>
        </div>
    </x-card>
</section>
