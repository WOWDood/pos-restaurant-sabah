<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
    $this->category = Category::factory()->create(['code' => 'Food']);
});

it('can view items page', function () {
    $this->get(route('items'))->assertOk();
});

it('can create an item', function () {
    Livewire\Livewire::test('pages::items')
        ->set('code', 'B1')
        ->set('category_id', $this->category->id)
        ->set('description', 'Burger')
        ->set('unit_price', 12.50)
        ->call('save');

    expect(Item::where('code', 'B1')->exists())->toBeTrue();
});

it('validates unique code and price', function () {
    Item::factory()->create(['code' => 'X1', 'category_id' => $this->category->id]);

    Livewire\Livewire::test('pages::items')
        ->set('code', 'X1')
        ->set('category_id', $this->category->id)
        ->set('description', 'Dup')
        ->set('unit_price', -1)
        ->call('save')
        ->assertHasErrors(['code', 'unit_price']);
});

it('can filter by category', function () {
    $other = Category::factory()->create(['code' => 'Drink']);
    Item::factory()->create(['code' => 'F1', 'category_id' => $this->category->id]);
    Item::factory()->create(['code' => 'D1', 'category_id' => $other->id]);

    $component = Livewire\Livewire::test('pages::items')
        ->set('categoryFilter', $this->category->id);

    $codes = $component->get('items')->pluck('code')->toArray();
    expect($codes)->toContain('F1')
        ->and($codes)->not->toContain('D1');
});

it('can delete an item', function () {
    $item = Item::factory()->create(['code' => 'Del', 'category_id' => $this->category->id]);

    Livewire\Livewire::test('pages::items')
        ->call('confirmDelete', $item->id)
        ->call('delete');

    expect(Item::find($item->id))->toBeNull();
});