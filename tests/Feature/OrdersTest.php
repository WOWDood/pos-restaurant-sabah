<?php

use App\Models\Order;
use App\Models\Receipt;
use App\Models\RestaurantTable;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
    $this->tableA = RestaurantTable::factory()->create(['name' => 'table-a']);
    $this->tableB = RestaurantTable::factory()->create(['name' => 'table-b']);
});

function createOrderForTable(RestaurantTable $table, User $user, string $status = Order::STATUS_NEW): Order
{
    return Order::create([
        'restaurant_table_id' => $table->id,
        'status' => $status,
        'total' => 0,
        'created_by' => $user->id,
    ]);
}

it('shows all orders after visiting a table-filtered orders page', function () {
    $tableOrder = createOrderForTable($this->tableA, $this->user);
    $otherOrder = createOrderForTable($this->tableB, $this->user);

    Livewire::withQueryParams(['table' => $this->tableA->name])
        ->test('pages::orders')
        ->assertSet('table', $this->tableA->name)
        ->assertSet('search', $this->tableA->name)
        ->assertSee('#'.$tableOrder->id)
        ->assertDontSee('#'.$otherOrder->id);

    Livewire::withQueryParams([])
        ->test('pages::orders')
        ->assertSet('table', null)
        ->assertSet('search', '')
        ->assertSee('#'.$tableOrder->id)
        ->assertSee('#'.$otherOrder->id);
});

it('does not persist a status pill across visits', function () {
    createOrderForTable($this->tableA, $this->user, Order::STATUS_PAID);
    createOrderForTable($this->tableB, $this->user, Order::STATUS_NEW);

    Livewire::test('pages::orders')
        ->call('setStatus', Order::STATUS_PAID)
        ->assertSee('Paid');

    Livewire::test('pages::orders')
        ->assertSet('status', 'all')
        ->assertSee('#'.Order::where('status', Order::STATUS_NEW)->first()->id)
        ->assertSee('#'.Order::where('status', Order::STATUS_PAID)->first()->id);
});

it('resets pagination when the table filter or search changes', function () {
    foreach (range(1, 11) as $index) {
        createOrderForTable($index === 11 ? $this->tableB : $this->tableA, $this->user);
    }

    $component = Livewire::test('pages::orders')
        ->call('nextPage')
        ->assertSet('paginators.page', 2)
        ->set('search', 'table-b')
        ->assertSet('paginators.page', 1)
        ->call('nextPage')
        ->set('table', 'table-a')
        ->call('clearTableFilter')
        ->assertSet('paginators.page', 1);

    expect($component->get('search'))->toBe('');
});

it('opens order detail and only opens receipt print for paid orders', function () {
    $unpaid = createOrderForTable($this->tableA, $this->user);
    $paid = createOrderForTable($this->tableB, $this->user, Order::STATUS_PAID);
    Receipt::create([
        'order_id' => $paid->id,
        'receipt_number' => 'RCPT-'.str_pad((string) $paid->id, 6, '0', STR_PAD_LEFT),
    ]);

    Livewire::test('pages::orders')
        ->call('openOrderDetail', $unpaid->id)
        ->assertSet('showDetailModal', true)
        ->assertSee('Order Detail')
        ->call('closeOrderDetail')
        ->call('openReceipt', $unpaid->id)
        ->assertSet('showReceiptModal', false)
        ->call('openReceipt', $paid->id)
        ->assertSet('showReceiptModal', true)
        ->assertSee('id="receipt-print"', false)
        ->assertSee('Thank you for your visit!');
});

it('does not load receipt data while viewing an order detail', function () {
    $order = createOrderForTable($this->tableA, $this->user);

    $component = Livewire::test('pages::orders')
        ->call('openOrderDetail', $order->id)
        ->assertSet('showDetailModal', true)
        ->assertSet('showReceiptModal', false);

    expect($component->get('selectedOrder')->relationLoaded('receipt'))->toBeFalse();
});
