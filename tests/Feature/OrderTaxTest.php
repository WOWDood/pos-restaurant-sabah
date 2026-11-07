<?php

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\RestaurantTable;
use App\Models\User;
use Livewire\Livewire;

it('snapshots tax settings on new bills and preserves existing bills', function () {
    $staff = User::factory()->create();
    $table = RestaurantTable::factory()->create();
    $oldOrder = Order::factory()->create(['restaurant_table_id' => $table->id]);
    BusinessSetting::create(['key' => 'tax_enabled', 'value' => '1']);
    BusinessSetting::create(['key' => 'tax_rate', 'value' => '6.00']);
    BusinessSetting::create(['key' => 'tax_name', 'value' => 'SST']);
    Livewire::actingAs($staff)->test('pages::pos')->call('openTable', $table->id)->call('createOrder');
    $order = Order::latest('id')->first();
    expect($order->tax_rate)->toBe('6.00')->and($order->tax_name)->toBe('SST');
    expect($oldOrder->fresh()->tax_rate)->toBe('0.00');
    BusinessSetting::where('key', 'tax_rate')->update(['value' => '8.00']);
    expect($order->fresh()->tax_rate)->toBe('6.00');
});

it('adds tax to server priced bills and pays the exact total with a receipt breakdown', function () {
    BusinessSetting::create(['key' => 'tax_enabled', 'value' => '1']);
    BusinessSetting::create(['key' => 'tax_rate', 'value' => '6.00']);
    BusinessSetting::create(['key' => 'tax_name', 'value' => 'SST']);
    BusinessSetting::create(['key' => 'receipt_footer', 'value' => 'Come back soon!']);
    $staff = User::factory()->create();
    $table = RestaurantTable::factory()->create();
    $category = Category::factory()->create();
    $item = Item::factory()->create(['category_id' => $category->id, 'unit_price' => 19.99, 'active' => true]);
    $method = PaymentMethod::factory()->create(['active' => true, 'ref_required' => false]);
    $order = Order::factory()->create(['restaurant_table_id' => $table->id]);

    $component = Livewire::actingAs($staff)->test('pages::pos-order', ['order' => $order])
        ->call('openAddItemModal')->call('selectItem', $item->id)->call('addToOrder')->assertHasNoErrors();
    expect($order->fresh())->subtotal->toBe('19.99')->tax_amount->toBe('1.20')->total->toBe('21.19');

    $component->call('openPaymentModal')->call('selectPaymentMethod', $method->id)
        ->call('confirmPayment')->call('payNow')->assertHasNoErrors()
        ->assertSee('SST (6.00%)')->assertSee('RM1.20')->assertSee('RM21.19')->assertSee('Come back soon!');
    expect($order->fresh()->status)->toBe(Order::STATUS_PAID);
    expect($order->payments()->sole()->amount)->toBe('21.19');

    BusinessSetting::where('key', 'tax_rate')->update(['value' => '8.00']);
    Livewire::test('pages::orders')->call('openReceipt', $order->id)
        ->assertSee('SST (6.00%)')->assertSee('RM1.20')->assertSee('RM21.19');
});

it('rounds tax to one sen on each bill', function () {
    $category = Category::factory()->create();
    $item = Item::factory()->create(['category_id' => $category->id]);
    $order = Order::factory()->create(['tax_rate' => 6, 'tax_name' => 'Tax']);
    $order->items()->create(['item_id' => $item->id, 'item_code' => $item->code,
        'item_description' => $item->description, 'unit_price' => 0.25, 'quantity' => 1, 'subtotal' => 0.25]);
    $order->recalculateTotals();
    expect($order->fresh())->subtotal->toBe('0.25')->tax_amount->toBe('0.02')->total->toBe('0.27');
});

it('does not apply a stored rate when tax is disabled', function () {
    BusinessSetting::create(['key' => 'tax_enabled', 'value' => '0']);
    BusinessSetting::create(['key' => 'tax_rate', 'value' => '6.00']);
    $order = Order::factory()->create();
    expect($order->fresh()->tax_rate)->toBe('0.00');
});
