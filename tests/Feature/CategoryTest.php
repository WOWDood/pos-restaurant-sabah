<?php

use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

it('can view categories page', function () {
    $this->get(route('categories'))->assertOk();
});

it('can create a category', function () {
    Livewire\Livewire::test('pages::categories')
        ->set('code', 'Snacks')
        ->set('description', 'Snacks')
        ->set('sort_order', 3)
        ->call('save');

    expect(Category::where('code', 'Snacks')->exists())->toBeTrue();
});

it('validates unique code', function () {
    Category::factory()->create(['code' => 'Drink']);

    Livewire\Livewire::test('pages::categories')
        ->set('code', 'Drink')
        ->set('description', 'Dup')
        ->set('sort_order', 1)
        ->call('save')
        ->assertHasErrors(['code']);
});

it('can edit a category', function () {
    $category = Category::factory()->create(['code' => 'Old', 'description' => 'Old desc', 'sort_order' => 1]);

    Livewire\Livewire::test('pages::categories')
        ->call('openEdit', $category->id)
        ->set('code', 'New')
        ->set('description', 'New desc')
        ->set('sort_order', 2)
        ->call('save');

    $category->refresh();
    expect($category->code)->toBe('New')
        ->and($category->description)->toBe('New desc')
        ->and($category->sort_order)->toBe(2);
});

it('can delete a category without items', function () {
    $category = Category::factory()->create(['code' => 'ToDelete']);

    Livewire\Livewire::test('pages::categories')
        ->call('confirmDelete', $category->id)
        ->call('delete');

    expect(Category::find($category->id))->toBeNull();
});

it('cannot delete a category with items', function () {
    $category = Category::factory()->create(['code' => 'HasItems']);
    \App\Models\Item::factory()->create(['category_id' => $category->id]);

    Livewire\Livewire::test('pages::categories')
        ->call('confirmDelete', $category->id)
        ->call('delete')
        ->assertHasErrors(['delete']);

    expect(Category::find($category->id))->not->toBeNull();
});

it('can search categories', function () {
    Category::factory()->create(['code' => 'Alpha', 'description' => 'Alpha desc']);
    Category::factory()->create(['code' => 'Beta', 'description' => 'Beta desc']);

    $component = Livewire\Livewire::test('pages::categories')
        ->set('search', 'Alpha');

    expect($component->get('categories')->pluck('code')->toArray())->toContain('Alpha')
        ->and($component->get('categories')->pluck('code')->toArray())->not->toContain('Beta');
});