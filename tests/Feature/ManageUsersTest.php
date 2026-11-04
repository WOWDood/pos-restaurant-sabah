<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('restricts the user dashboard to administrators', function () {
    $this->get(route('admin.users'))->assertRedirect(route('login'));
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'S01']);
    $this->actingAs($staff)->get(route('admin.users'))->assertForbidden();
    Livewire::test('pages::manage-users')->assertForbidden();
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']);
    $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee('Manage Users');
});

it('creates a user with a hashed password and selected access', function () {
    $this->actingAs(User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']));
    Livewire::test('pages::manage-users')->call('openCreate')
        ->set('name', 'New Staff')->set('email', 'staff@example.com')->set('code', 'S01')
        ->set('userType', User::TYPE_STAFF)->set('password', 'password123')->set('password_confirmation', 'password123')
        ->call('save')->assertHasNoErrors();
    $user = User::where('email', 'staff@example.com')->sole();
    expect($user->user_type)->toBe(User::TYPE_STAFF)->and($user->code)->toBe('S01')
        ->and(Hash::check('password123', $user->password))->toBeTrue()->and($user->email_verified_at)->toBeNull();
});

it('edits a user and requires verification after an email change', function () {
    $this->actingAs(User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']));
    $user = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'S01']);
    Livewire::test('pages::manage-users')->call('openEdit', $user->id)
        ->set('name', 'Updated')->set('email', 'updated@example.com')->set('userType', User::TYPE_ADMIN)
        ->call('save')->assertHasNoErrors();
    $user->refresh();
    expect($user->name)->toBe('Updated')->and($user->user_type)->toBe(User::TYPE_ADMIN)
        ->and($user->email_verified_at)->toBeNull();
});

it('validates unique email and password confirmation', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']);
    $this->actingAs($admin);
    Livewire::test('pages::manage-users')->call('openCreate')
        ->set('name', 'Staff')->set('email', $admin->email)
        ->set('password', 'password123')->set('password_confirmation', 'different')
        ->call('save')->assertHasErrors(['email', 'password']);
    expect(User::count())->toBe(1);
});

it('prevents administrators from removing their own access', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']);
    $this->actingAs($admin);
    Livewire::test('pages::manage-users')->call('openEdit', $admin->id)
        ->set('userType', User::TYPE_STAFF)->call('save')->assertHasErrors('userType');
    expect($admin->fresh()->user_type)->toBe(User::TYPE_ADMIN);
});

it('rejects actions after an administrator loses access', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']);
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'S01']);
    $this->actingAs($admin);
    $component = Livewire::test('pages::manage-users')->call('openEdit', $staff->id);
    $admin->update(['user_type' => User::TYPE_STAFF]);
    $component->set('name', 'Changed')->assertForbidden();
    expect($staff->fresh()->name)->not->toBe('Changed');
});

it('promotes only the requested existing account through the command', function () {
    $user = User::factory()->create(['user_type' => User::TYPE_STAFF]);
    $this->artisan('users:promote-admin', ['email' => $user->email])->assertSuccessful();
    expect($user->fresh()->user_type)->toBe(User::TYPE_ADMIN);
    $this->artisan('users:promote-admin', ['email' => 'missing@example.com'])->assertFailed();
});

it('creates a verified administrator with an interactively chosen password', function () {
    $this->artisan('users:create-admin', ['email' => 'admin@example.com', 'name' => 'Administrator', 'code' => 'A01'])
        ->expectsQuestion('Choose a password', 'password123')
        ->expectsQuestion('Confirm the password', 'password123')
        ->assertSuccessful();

    $admin = User::where('email', 'admin@example.com')->sole();
    expect($admin->isAdministrator())->toBeTrue()->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('password123', $admin->password))->toBeTrue();
});

it('refuses administrator creation with mismatched passwords', function () {
    $this->artisan('users:create-admin', ['email' => 'admin@example.com', 'name' => 'Administrator', 'code' => 'A01'])
        ->expectsQuestion('Choose a password', 'password123')
        ->expectsQuestion('Confirm the password', 'different')
        ->assertFailed();
    expect(User::count())->toBe(0);
});
