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

it('can upload a photo', function () {
    \Illuminate\Support\Facades\Storage::fake('public');
    $item = Item::factory()->create(['code' => 'Photo', 'category_id' => $this->category->id]);
    $file = \Illuminate\Http\UploadedFile::fake()->image('photo.jpg');

    Livewire\Livewire::test('pages::items')
        ->set('photoItemId', $item->id)
        ->set('photoFiles', [$file])
        ->call('uploadPhotos');

    expect($item->photos()->count())->toBe(1);
});

it('can add a modifier group and option', function () {
    $item = Item::factory()->create(['code' => 'Mod', 'category_id' => $this->category->id]);

    Livewire\Livewire::test('pages::items')
        ->set('modifierItemId', $item->id)
        ->set('newGroupName', 'Toppings')
        ->set('newGroupMin', 0)
        ->set('newGroupMax', 1)
        ->call('addGroup');

    $group = \App\Models\ModifierGroup::where('item_id', $item->id)->first();
    expect($group)->not->toBeNull();

    Livewire\Livewire::test('pages::items')
        ->set('modifierItemId', $item->id)
        ->set('newOptionGroupId', $group->id)
        ->set('newOptionEn', 'Cheese')
        ->call('addOption');

    expect(\App\Models\ModifierOption::where('modifier_group_id', $group->id)->where('name_en', 'Cheese')->exists())->toBeTrue();
});