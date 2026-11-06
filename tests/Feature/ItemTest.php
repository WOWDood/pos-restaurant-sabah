<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
    $this->category = Category::factory()->create(['code' => 'Food']);
});

it('can view items page', function () {
    $this->get(route('items'))->assertOk();
});

it('does not leak modifier modal marker and modal is closed by default', function () {
    $item = Item::factory()->create(['code' => 'ModTest', 'category_id' => $this->category->id]);

    Livewire\Livewire::test('pages::items')
        ->assertDontSee('__MODIFIER_MODAL_START__')
        ->assertSet('showModifiersModal', false)
        ->call('openModifiers', $item->id)
        ->assertSet('showModifiersModal', true);
});

it('can create an item', function () {
    Livewire\Livewire::test('pages::items')
        ->set('code', 'B1')
        ->set('category_id', $this->category->id)
        ->set('description', 'Burger')
        ->set('unit_price', 12.50)
        ->call('save')
        ->assertSee('Item saved.');

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
        ->assertHasErrors(['code', 'unit_price'])
        ->assertDontSee('Item saved.');
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

it('does not delete an item that is used in an order', function () {
    $item = Item::factory()->create(['code' => 'Used', 'category_id' => $this->category->id]);
    $order = Order::create([
        'restaurant_table_id' => RestaurantTable::factory()->create()->id,
        'status' => Order::STATUS_NEW,
        'total' => 10,
        'created_by' => $this->user->id,
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'item_id' => $item->id,
        'item_code' => $item->code,
        'item_description' => $item->description,
        'quantity' => 1,
        'unit_price' => 10,
        'subtotal' => 10,
    ]);

    Livewire\Livewire::test('pages::items')
        ->call('confirmDelete', $item->id)
        ->call('delete')
        ->assertHasErrors('delete')
        ->assertSet('deleteId', $item->id);

    expect(Item::find($item->id))->not->toBeNull();
});

it('can upload a photo', function () {
    Storage::fake('public');
    $item = Item::factory()->create(['code' => 'Photo', 'category_id' => $this->category->id]);
    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire\Livewire::test('pages::items')
        ->set('photoItemId', $item->id)
        ->set('photoFiles', [$file])
        ->call('uploadPhotos');

    $photo = $item->photos()->first();
    expect($item->photos()->count())->toBe(1)
        ->and($photo->is_primary)->toBeTrue();
    Storage::disk('public')->assertExists($photo->path);
});

it('can add a modifier group and option', function () {
    $item = Item::factory()->create(['code' => 'Mod', 'category_id' => $this->category->id]);

    Livewire\Livewire::test('pages::items')
        ->set('modifierItemId', $item->id)
        ->set('newGroupName', 'Toppings')
        ->set('newGroupMin', 0)
        ->set('newGroupMax', 1)
        ->call('saveGroup');

    $group = ModifierGroup::where('item_id', $item->id)->first();
    expect($group)->not->toBeNull();

    Livewire\Livewire::test('pages::items')
        ->set('modifierItemId', $item->id)
        ->set('newOptionGroupId', $group->id)
        ->set('newOptionEn', 'Cheese')
        ->call('saveOption');

    expect(ModifierOption::where('modifier_group_id', $group->id)->where('name_en', 'Cheese')->exists())->toBeTrue();
});
