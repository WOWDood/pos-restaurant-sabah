<?php

use App\Models\RestaurantTable;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

it('can view tables page', function () {
    $this->get(route('tables'))->assertOk();
});

it('can view table qr page', function () {
    $this->get(route('table-qr'))->assertOk();
});

it('can create a table', function () {
    Livewire\Livewire::test('pages::tables')
        ->set('name', 'table99')
        ->call('save');

    expect(RestaurantTable::where('name', 'table99')->exists())->toBeTrue();
});

it('validates unique description', function () {
    RestaurantTable::factory()->create(['name' => 'table01']);

    Livewire\Livewire::test('pages::tables')
        ->set('name', 'table01')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('can delete a table', function () {
    $t = RestaurantTable::factory()->create(['name' => 'ToDelete']);

    Livewire\Livewire::test('pages::tables')
        ->call('confirmDelete', $t->id)
        ->call('delete');

    expect(RestaurantTable::find($t->id))->toBeNull();
});