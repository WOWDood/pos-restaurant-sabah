<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
    $this->table = RestaurantTable::factory()->create(['name' => 'table99']);
    $this->category = Category::factory()->create(['code' => 'Food', 'description' => 'Food']);
});

function newPosOrder(User $user, RestaurantTable $table, string $status = Order::STATUS_NEW): Order
{
    return Order::create([
        'restaurant_table_id' => $table->id,
        'status' => $status,
        'total' => 0,
        'created_by' => $user->id,
    ]);
}

it('can view pos page', function () {
    $this->get(route('pos'))->assertOk();
});

it('creates an order for a table', function () {
    Livewire::test('pages::pos')
        ->call('openTable', $this->table->id)
        ->call('createOrder');

    expect(Order::where('restaurant_table_id', $this->table->id)->where('status', Order::STATUS_NEW)->exists())->toBeTrue();
});

it('adds an item without modifiers and updates the order total', function () {
    $order = newPosOrder($this->user, $this->table);
    $item = Item::factory()->create([
        'category_id' => $this->category->id,
        'code' => 'A1',
        'description' => 'Mee Sup Inanam',
        'unit_price' => 8,
        'active' => true,
    ]);

    Livewire::test('pages::pos-order', ['order' => $order])
        ->call('openAddItemModal')
        ->call('selectItem', $item->id)
        ->set('quantity', 2)
        ->set('notes', 'Less spicy')
        ->call('addToOrder')
        ->assertHasNoErrors();

    $line = OrderItem::first();

    expect($line)->not->toBeNull()
        ->and($line->item_code)->toBe('A1')
        ->and($line->item_description)->toBe('Mee Sup Inanam')
        ->and($line->quantity)->toBe(2)
        ->and((float) $line->unit_price)->toBe(8.0)
        ->and((float) $line->subtotal)->toBe(16.0)
        ->and($line->notes)->toBe('Less spicy')
        ->and((float) $order->fresh()->total)->toBe(16.0);
});

it('adds an item with modifiers and uses adjustments in the price', function () {
    $order = newPosOrder($this->user, $this->table);
    $item = Item::factory()->create([
        'category_id' => $this->category->id,
        'unit_price' => 8,
        'active' => true,
    ]);
    $group = ModifierGroup::create([
        'item_id' => $item->id,
        'name' => 'food',
        'min_select' => 1,
        'max_select' => 2,
        'sort_order' => 1,
    ]);
    $cheese = ModifierOption::create([
        'modifier_group_id' => $group->id,
        'name_en' => 'Cheese',
        'name_zh' => '芝士',
        'price_adjustment' => 1.50,
        'sort_order' => 1,
    ]);
    $egg = ModifierOption::create([
        'modifier_group_id' => $group->id,
        'name_en' => 'Egg',
        'name_zh' => '蛋',
        'price_adjustment' => 0.50,
        'sort_order' => 2,
    ]);

    Livewire::test('pages::pos-order', ['order' => $order])
        ->call('openAddItemModal')
        ->call('selectItem', $item->id)
        ->set('quantity', 2)
        ->set("selections.{$group->id}", [$cheese->id, $egg->id])
        ->assertSee('RM10.00')
        ->call('addToOrder')
        ->assertHasNoErrors();

    $line = OrderItem::first();

    expect((float) $line->unit_price)->toBe(10.0)
        ->and((float) $line->subtotal)->toBe(20.0)
        ->and($line->modifiers)->toHaveCount(2)
        ->and(collect($line->modifiers)->pluck('name_en')->all())->toBe(['Cheese', 'Egg'])
        ->and((float) $order->fresh()->total)->toBe(20.0);
});

it('enforces modifier minimum and maximum selections on the server', function () {
    $order = newPosOrder($this->user, $this->table);
    $item = Item::factory()->create([
        'category_id' => $this->category->id,
        'unit_price' => 8,
        'active' => true,
    ]);
    $group = ModifierGroup::create([
        'item_id' => $item->id,
        'name' => 'food',
        'min_select' => 2,
        'max_select' => 2,
        'sort_order' => 1,
    ]);
    $options = collect(['A', 'B', 'C'])->map(fn ($name, $index) => ModifierOption::create([
        'modifier_group_id' => $group->id,
        'name_en' => $name,
        'sort_order' => $index + 1,
    ]));

    Livewire::test('pages::pos-order', ['order' => $order])
        ->call('openAddItemModal')
        ->call('selectItem', $item->id)
        ->set("selections.{$group->id}", [$options[0]->id])
        ->call('addToOrder')
        ->assertHasErrors("selections.{$group->id}");

    Livewire::test('pages::pos-order', ['order' => $order])
        ->call('openAddItemModal')
        ->call('selectItem', $item->id)
        ->set("selections.{$group->id}", $options->pluck('id')->all())
        ->call('addToOrder')
        ->assertHasErrors("selections.{$group->id}");

    expect(OrderItem::count())->toBe(0);
});

it('only lists active items in the add item search', function () {
    $active = Item::factory()->create(['category_id' => $this->category->id, 'active' => true]);
    $inactive = Item::factory()->create(['category_id' => $this->category->id, 'active' => false]);

    $component = Livewire::test('pages::pos-order', ['order' => newPosOrder($this->user, $this->table)])
        ->call('openAddItemModal');

    $ids = $component->get('availableItems')->pluck('id');

    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('rejects adding items to paid or cancelled orders', function (string $status) {
    $order = newPosOrder($this->user, $this->table, $status);
    $item = Item::factory()->create(['category_id' => $this->category->id, 'active' => true]);

    Livewire::test('pages::pos-order', ['order' => $order])
        ->call('openAddItemModal')
        ->assertHasErrors('order')
        ->call('selectItem', $item->id)
        ->set('quantity', 1)
        ->call('addToOrder')
        ->assertHasErrors('order');

    expect(OrderItem::count())->toBe(0);
})->with([Order::STATUS_PAID, Order::STATUS_CANCELLED]);

it('adds a combo item with both groups end to end', function () {
    $order = newPosOrder($this->user, $this->table);
    $combo = Item::factory()->create([
        'category_id' => $this->category->id,
        'code' => 'setLA',
        'description' => 'Set Lunch A',
        'unit_price' => 20,
        'is_combo' => true,
        'active' => true,
    ]);
    $drink = ModifierGroup::create([
        'item_id' => $combo->id,
        'name' => 'Drink',
        'min_select' => 1,
        'max_select' => 1,
        'sort_order' => 1,
    ]);
    $drinkOption = ModifierOption::create([
        'modifier_group_id' => $drink->id,
        'name_en' => 'D1 - Coffee',
        'name_zh' => '咖啡',
        'sort_order' => 1,
    ]);
    $main = ModifierGroup::create([
        'item_id' => $combo->id,
        'name' => 'Main Dish',
        'min_select' => 1,
        'max_select' => 1,
        'sort_order' => 2,
    ]);
    $mainOption = ModifierOption::create([
        'modifier_group_id' => $main->id,
        'name_en' => 'C1 - Nasi Goreng',
        'name_zh' => '炒饭',
        'sort_order' => 1,
    ]);

    Livewire::test('pages::pos-order', ['order' => $order])
        ->call('openAddItemModal')
        ->call('selectItem', $combo->id)
        ->set("selections.{$drink->id}", $drinkOption->id)
        ->set("selections.{$main->id}", $mainOption->id)
        ->call('addToOrder')
        ->assertHasNoErrors();

    $line = OrderItem::first();

    expect($line->modifiers)->toHaveCount(2)
        ->and(collect($line->modifiers)->pluck('group_name')->all())->toBe(['Drink', 'Main Dish'])
        ->and(collect($line->modifiers)->pluck('name_en')->all())->toBe(['D1 - Coffee', 'C1 - Nasi Goreng'])
        ->and((float) $line->unit_price)->toBe(20.0);
});
