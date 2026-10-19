<?php

use App\Models\RestaurantTable;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

it('can view pos page', function () {
    $this->get(route('pos'))->assertOk();
});

it('creates an order for a table', function () {
    $table = RestaurantTable::factory()->create(['name' => 'table99']);

    Livewire\Livewire::test('pages::pos')
        ->call('openTable', $table->id)
        ->call('createOrder');

    expect(\App\Models\Order::where('restaurant_table_id', $table->id)->where('status', 'new')->exists())->toBeTrue();
});