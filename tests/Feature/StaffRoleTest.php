<?php

use App\Models\StaffRole;
use App\Models\User;
use Livewire\Livewire;

it('keeps staff and role administration inside admin shop settings', function () {
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'S01']);
    $this->actingAs($staff)->get(route('admin.staff-roles'))->assertForbidden();
    Livewire::test('pages::staff-roles')->assertForbidden();
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']);
    $this->actingAs($admin)->get(route('admin.staff-roles'))->assertOk()->assertSee('Shop Details')->assertSee('Manage Users');
    $this->get('/admin/users')->assertRedirect(route('admin.users'));
});

it('creates and edits a custom role', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    Livewire::actingAs($admin)->test('pages::staff-roles')->call('openCreate')
        ->set('name', 'Cashier')->set('permissions', ['use-pos'])
        ->call('save')->assertHasNoErrors();
    $role = StaffRole::where('name', 'Cashier')->sole();
    expect($role->permissions)->toBe(['use-pos']);
    Livewire::test('pages::staff-roles')->call('openEdit', $role->id)
        ->set('name', 'Menu manager')->set('permissions', ['manage-menu'])
        ->call('save')->assertHasNoErrors();
    expect($role->fresh())->name->toBe('Menu manager')->permissions->toBe(['manage-menu']);
});

it('does not allow custom roles to grant administrator powers or use built in names', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    $component = Livewire::actingAs($admin)->test('pages::staff-roles')->call('openCreate')
        ->set('name', 'Super staff')->set('permissions', ['manage-shop'])
        ->call('save')->assertHasErrors('permissions.0');
    $component->set('name', 'Administrator')->set('permissions', [])
        ->call('save')->assertHasErrors('name');
    expect(StaffRole::count())->toBe(0);
});

it('assigns a custom role through user management', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']);
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'S01']);
    $role = StaffRole::factory()->create(['permissions' => ['use-pos']]);
    Livewire::actingAs($admin)->test('pages::manage-users')->call('openEdit', $staff->id)
        ->set('staffRoleId', $role->id)->call('save')->assertHasNoErrors();
    expect($staff->fresh()->staff_role_id)->toBe($role->id);
    $staff->refresh();
    expect($staff->can('use-pos'))->toBeTrue()->and($staff->can('view-orders'))->toBeTrue()
        ->and($staff->can('manage-menu'))->toBeFalse()->and($staff->can('manage-shop'))->toBeFalse();
});

it('rejects nonexistent staff roles', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN, 'code' => 'A01']);
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'code' => 'S01']);
    Livewire::actingAs($admin)->test('pages::manage-users')->call('openEdit', $staff->id)
        ->set('staffRoleId', 999999)->call('save')->assertHasErrors('staffRoleId');
    expect($staff->fresh()->staff_role_id)->toBeNull();
});

it('enforces operational permissions on requests and livewire actions', function (string $route, string $component, string $permission) {
    $role = StaffRole::factory()->create(['permissions' => []]);
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'staff_role_id' => $role->id]);
    $this->actingAs($staff)->get(route($route))->assertForbidden();
    Livewire::test($component)->assertForbidden();
    $role->update(['permissions' => [$permission]]);
    $staff->unsetRelation('staffRole');
    $this->get(route($route))->assertOk();
})->with([
    ['categories', 'pages::categories', 'manage-menu'],
    ['items', 'pages::items', 'manage-menu'],
    ['tables', 'pages::tables', 'manage-tables'],
    ['table-qr', 'pages::table-qr', 'manage-tables'],
    ['payment-methods', 'pages::payment-methods', 'manage-payment-methods'],
    ['orders', 'pages::orders', 'view-orders'],
    ['pos', 'pages::pos', 'use-pos'],
]);

it('revokes access on the next action when role permissions change', function () {
    $role = StaffRole::factory()->create(['permissions' => ['manage-menu']]);
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'staff_role_id' => $role->id]);
    $component = Livewire::actingAs($staff)->test('pages::categories');
    $role->update(['permissions' => []]);
    $component->call('openCreate')->assertForbidden();
});

it('sends a staff member without dashboard access to their profile', function () {
    $role = StaffRole::factory()->create(['permissions' => []]);
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF, 'staff_role_id' => $role->id]);
    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('profile'));
    $this->get(route('profile'))->assertOk()->assertDontSee('Shop Settings')->assertDontSee('href="'.route('pos').'"', false);
});

it('preserves full operational access for existing staff without a custom role', function () {
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF]);
    foreach (array_keys(StaffRole::PERMISSIONS) as $permission) {
        expect($staff->can($permission))->toBeTrue();
    }
    expect($staff->can('manage-shop'))->toBeFalse()->and($staff->can('manage-users'))->toBeFalse();
});
