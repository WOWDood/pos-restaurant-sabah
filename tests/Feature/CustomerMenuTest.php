<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->table = RestaurantTable::factory()->create();
    $this->category = Category::factory()->create();
    $this->item = Item::factory()->create(['category_id' => $this->category->id, 'unit_price' => 8, 'active' => true]);
    $this->group = ModifierGroup::create(['item_id' => $this->item->id, 'name' => 'Extras', 'min_select' => 1, 'max_select' => 1, 'sort_order' => 1]);
    $this->option = ModifierOption::create(['modifier_group_id' => $this->group->id, 'name_en' => 'Egg', 'price_adjustment' => 1.5, 'sort_order' => 1]);
});

it('returns a friendly 404 for unknown tables', function () {
    $this->get('/order/999999')->assertNotFound()->assertSee('Table not found');
    $this->get('/order/not-a-table')->assertNotFound()->assertSee('Table not found');
});

it('shows only active items on the public menu', function () {
    $inactive = Item::factory()->create(['category_id' => $this->category->id, 'active' => false]);
    $this->get(route('customer-order.create', $this->table))->assertOk()
        ->assertSee($this->item->description)->assertDontSee($inactive->description);
    Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $inactive->id)->assertNotFound();
});

it('enforces minimum and maximum modifier choices', function () {
    $other = ModifierOption::create(['modifier_group_id' => $this->group->id, 'name_en' => 'Cheese', 'price_adjustment' => 2, 'sort_order' => 2]);
    $menu = Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $this->item->id)->call('addToCart')->assertHasErrors('selections.'.$this->group->id)
        ->set('selections.'.$this->group->id, [$this->option->id, $other->id])->call('addToCart')
        ->assertHasErrors('selections.'.$this->group->id);
    expect($menu->get('cart'))->toBeEmpty()->and(Order::count())->toBe(0);
});

it('initializes multi-choice groups as arrays for independent checkbox selection', function () {
    $this->group->update(['max_select' => 2]);
    Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $this->item->id)->assertSet('selections.'.$this->group->id, []);
});

it('uses current database prices and calculates cart totals', function () {
    $menu = Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $this->item->id)->set('selections.'.$this->group->id, $this->option->id)->set('quantity', 2);
    $this->item->update(['unit_price' => 10]);
    $menu->call('addToCart')->assertHasNoErrors();
    $cart = $menu->get('cart');
    expect($cart[0]['unit_price'])->toBe(11.5)->and($menu->get('cartCount'))->toBe(2)->and($menu->get('cartTotal'))->toBe(23.0);
    $menu->call('changeCartQuantity', $cart[0]['key'], 1);
    expect($menu->get('cartTotal'))->toBe(34.5);
    $menu->call('removeFromCart', $cart[0]['key']);
    expect($menu->get('cart'))->toBeEmpty()->and(Order::count())->toBe(0);
});

it('keeps carts separate by table and does not accept checkout', function () {
    Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $this->item->id)->set('selections.'.$this->group->id, $this->option->id)->call('addToCart');
    $anotherTable = RestaurantTable::factory()->create();
    Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])->assertCount('cart', 1);
    Livewire::test('pages::customer-menu', ['table' => $anotherTable, 'businessName' => 'Restaurant'])->assertSet('cart', []);
    $this->post('/order/'.$this->table->id)->assertMethodNotAllowed();
});

it('serves a small cached image only for active items', function () {
    Storage::fake('public');
    Storage::fake('local');
    $image = imagecreatetruecolor(800, 600);
    ob_start();
    imagejpeg($image);
    Storage::disk('public')->put('items/photo.jpg', ob_get_clean());
    $photo = ItemPhoto::create(['item_id' => $this->item->id, 'path' => 'items/photo.jpg', 'is_primary' => true]);

    $response = $this->get(route('customer-order.photo', $photo))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect(getimagesizefromstring($response->getContent())[0])->toBe(320);
    $this->item->update(['active' => false]);
    $this->get(route('customer-order.photo', $photo))->assertNotFound();
});
