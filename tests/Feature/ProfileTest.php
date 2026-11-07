<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('requires sign in for both profile addresses', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

it('shows the same profile options at both profile addresses', function () {
    $user = User::factory()->create(['code' => 'STAFF-01', 'user_type' => User::TYPE_STAFF]);

    $this->actingAs($user);

    foreach (['profile', 'profile.edit'] as $route) {
        $this->get(route($route))->assertOk()
            ->assertSee('My profile')->assertSee('Personal details')
            ->assertSee('Change password')->assertSee('Manage sign-in security')
            ->assertSee('STAFF-01')->assertSee('Staff');
    }
});

it('updates only the signed in users details and preserves verification when email is unchanged', function () {
    $user = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'STAFF-01']);
    $otherUser = User::factory()->create(['name' => 'Other staff']);

    Livewire::actingAs($user)->test('pages::profile')
        ->set('name', '  Updated name  ')
        ->call('updateProfileInformation')
        ->assertHasNoErrors()->assertRedirect(route('profile'));

    expect($user->fresh())->name->toBe('Updated name')
        ->email_verified_at->not->toBeNull()
        ->user_type->toBe(User::TYPE_STAFF)->code->toBe('STAFF-01');
    expect($otherUser->fresh()->name)->toBe('Other staff');
});

it('requires the correct current password to change email', function (string $password) {
    $user = User::factory()->create();
    $originalEmail = $user->email;

    Livewire::actingAs($user)->test('pages::profile')
        ->set('email', 'new-address@example.com')
        ->set('profile_password', $password)
        ->call('updateProfileInformation')
        ->assertHasErrors(['profile_password'])->assertSet('profile_password', '');

    expect($user->fresh()->email)->toBe($originalEmail);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
})->with(['missing password' => '', 'incorrect password' => 'incorrect-password']);

it('changes email after confirmation and requires verification again', function () {
    $user = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'STAFF-01']);

    Livewire::actingAs($user)->test('pages::profile')
        ->set('name', 'Updated staff')
        ->set('email', 'new-address@example.com')
        ->set('profile_password', 'password')
        ->call('updateProfileInformation')
        ->assertHasNoErrors()->assertRedirect(route('profile'));

    expect($user->fresh())->name->toBe('Updated staff')
        ->email->toBe('new-address@example.com')->email_verified_at->toBeNull()
        ->user_type->toBe(User::TYPE_STAFF)->code->toBe('STAFF-01');
});

it('rejects invalid personal details without saving changes', function (string $field, string $value, string $rule) {
    $user = User::factory()->create();
    $originalName = $user->name;
    $originalEmail = $user->email;

    Livewire::actingAs($user)->test('pages::profile')
        ->set($field, $value)->set('profile_password', 'password')
        ->call('updateProfileInformation')->assertHasErrors([$field => $rule]);

    expect($user->fresh()->name)->toBe($originalName);
    expect($user->fresh()->email)->toBe($originalEmail);
})->with([
    'blank name' => ['name', '   ', 'required'],
    'long name' => ['name', str_repeat('a', 256), 'max'],
    'blank email' => ['email', '', 'required'],
    'invalid email' => ['email', 'not-an-email', 'email'],
]);

it('rejects an email address already used by another account', function () {
    $user = User::factory()->create(['code' => 'STAFF-01']);
    $otherUser = User::factory()->create(['code' => 'STAFF-02']);

    Livewire::actingAs($user)->test('pages::profile')
        ->set('email', $otherUser->email)->set('profile_password', 'password')
        ->call('updateProfileInformation')->assertHasErrors(['email' => 'unique']);

    expect($user->fresh()->email)->toBe($user->email);
});

it('discards unsaved personal details', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::profile')
        ->set('name', 'Unsaved name')->set('email', 'unsaved@example.com')
        ->set('profile_password', 'password')->call('resetProfileInformation')
        ->assertSet('name', $user->name)->assertSet('email', $user->email)
        ->assertSet('profile_password', '')->assertHasNoErrors();
});

it('updates the password after confirming the current password and clears the fields', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::profile')
        ->set('current_password', 'password')->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')->call('updatePassword')
        ->assertHasNoErrors()->assertSet('current_password', '')
        ->assertSet('password', '')->assertSet('password_confirmation', '');

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects invalid password changes without changing the saved password', function (string $current, string $password, string $confirmation, string $field) {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::profile')
        ->set('current_password', $current)->set('password', $password)
        ->set('password_confirmation', $confirmation)->call('updatePassword')
        ->assertHasErrors([$field])->assertSet('current_password', '')
        ->assertSet('password', '')->assertSet('password_confirmation', '');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'missing current password' => ['', 'new-password', 'new-password', 'current_password'],
    'incorrect current password' => ['incorrect-password', 'new-password', 'new-password', 'current_password'],
    'short new password' => ['password', 'short', 'short', 'password'],
    'confirmation mismatch' => ['password', 'new-password', 'different-password', 'password'],
]);

it('sends verification to the saved email and limits repeated requests', function () {
    Notification::fake();
    $this->freezeTime();
    $user = User::factory()->unverified()->create();

    $component = Livewire::actingAs($user)->test('pages::profile')
        ->set('email', 'unsaved@example.com')->call('resendVerificationNotification')
        ->assertHasNoErrors()->call('resendVerificationNotification')
        ->assertHasErrors(['verification'])
        ->assertSee('Please wait a minute before requesting another verification email.');

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    expect($user->fresh()->email)->toBe($user->email);

    $this->travel(61)->seconds();
    $component->call('resendVerificationNotification')->assertHasNoErrors();
    Notification::assertSentToTimes($user, VerifyEmail::class, 2);
});

it('does not send verification for a verified email', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::profile')
        ->call('resendVerificationNotification')->assertHasNoErrors();

    Notification::assertNothingSent();
});
