<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\RestaurantTable;
use Livewire\Livewire;

beforeEach(function () {
    $this->table = RestaurantTable::factory()->create();
    $this->category = Category::factory()->create();
    $this->item = Item::factory()->create(['category_id' => $this->category->id, 'unit_price' => 8, 'active' => true]);
    $this->group = ModifierGroup::create(['item_id' => $this->item->id, 'name' => 'Extras', 'min_select' => 1, 'max_select' => 1, 'sort_order' => 1]);
    $this->option = ModifierOption::create(['modifier_group_id' => $this->group->id, 'name_en' => 'Egg', 'price_adjustment' => 1.5, 'sort_order' => 1]);
});

it('shows active menu items to customers', function () {
    $inactive = Item::factory()->create(['category_id' => $this->category->id, 'active' => false]);
    $this->get(route('customer-order.create', $this->table))->assertOk()->assertSee($this->item->description)->assertDontSee($inactive->description);
});

it('calculates a cart line from database prices without creating an order', function () {
    $menu = Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $this->item->id)->set('quantity', 2)->set('selections.'.$this->group->id, $this->option->id)->call('addToCart')->assertHasNoErrors();
    expect($menu->get('cartTotal'))->toBe(19.0)->and(Order::count())->toBe(0);
});

it('rejects missing or invalid modifiers without adding a cart line', function (bool $invalid) {
    $menu = Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $this->item->id)->set('selections.'.$this->group->id, $invalid ? 999999 : null)
        ->call('addToCart')->assertHasErrors('selections.'.$this->group->id);
    expect($menu->get('cart'))->toBeEmpty()->and(Order::count())->toBe(0);
})->with([false, true]);

it('does not add items that become inactive', function () {
    $menu = Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])->call('openItem', $this->item->id);
    $this->item->update(['active' => false]);
    $menu->call('addToCart')->assertHasErrors('item');
    expect($menu->get('cart'))->toBeEmpty();
});

it('rejects quantities outside the allowed range', function () {
    Livewire::test('pages::customer-menu', ['table' => $this->table, 'businessName' => 'Restaurant'])
        ->call('openItem', $this->item->id)->set('quantity', 0)->call('addToCart')->assertHasErrors('quantity');
    expect(Order::count())->toBe(0);
});
