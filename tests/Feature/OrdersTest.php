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
        ->assertSee('Order #'.$unpaid->id)
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

it('finds an exact order number with or without a hash even without a table', function (string $prefix) {
    $order = Order::factory()->create(['restaurant_table_id' => null]);
    Order::factory()->create(['restaurant_table_id' => $this->tableA->id]);

    $component = Livewire::test('pages::orders')->set('search', $prefix.$order->id);

    expect($component->get('orders')->pluck('id')->all())->toBe([$order->id]);
})->with(['', '#']);

it('applies inclusive Malaysian creation dates to both results and unpaid counts', function () {
    Order::factory()->create(['created_at' => '2026-11-05 15:59:59']);
    $start = Order::factory()->create(['created_at' => '2026-11-05 16:00:00']);
    $end = Order::factory()->create(['created_at' => '2026-11-06 15:59:59', 'status' => Order::STATUS_PAID]);
    Order::factory()->create(['created_at' => '2026-11-06 16:00:00']);

    $component = Livewire::test('pages::orders')
        ->set('dateFrom', '2026-11-06')
        ->set('dateTo', '2026-11-06')
        ->assertHasNoErrors();

    expect($component->get('orders')->pluck('id')->all())->toBe([$end->id, $start->id]);
    expect($component->get('unpaidCount'))->toBe(1);

    $component->call('setStatus', Order::STATUS_NEW)->set('search', '#'.$end->id);
    expect($component->get('orders')->total())->toBe(0);
    expect($component->get('unpaidCount'))->toBe(0);
});

it('supports either end of the date range on its own', function (string $field, string $expected) {
    $older = Order::factory()->create(['created_at' => '2026-11-05 15:59:59']);
    $newer = Order::factory()->create(['created_at' => '2026-11-07 16:00:00']);

    $component = Livewire::test('pages::orders')->set($field, '2026-11-06')->assertHasNoErrors();

    expect($component->get('orders')->pluck('id')->all())->toBe([$expected === 'older' ? $older->id : $newer->id]);
})->with([['dateFrom', 'newer'], ['dateTo', 'older']]);

it('rejects invalid or reversed dates without running an invalid database date query', function () {
    Order::factory()->create();
    $component = Livewire::test('pages::orders')
        ->set('dateFrom', '2026-02-30')
        ->assertHasErrors(['dateFrom' => 'date_format']);
    expect($component->get('orders')->total())->toBe(0);

    $component->set('dateFrom', '2026-11-07')
        ->set('dateTo', '2026-11-06')
        ->assertHasErrors(['dateTo' => 'after_or_equal']);
    expect($component->get('orders')->total())->toBe(0);

    $component->set('dateTo', '2026-11-08')->assertHasNoErrors();
});

it('clears all filters and resets pagination after date changes', function () {
    Order::factory()->count(11)->create(['restaurant_table_id' => $this->tableA->id]);

    Livewire::test('pages::orders')
        ->call('nextPage')->assertSet('paginators.page', 2)
        ->set('dateFrom', '2026-01-01')->assertSet('paginators.page', 1)
        ->set('dateTo', '2026-12-31')
        ->set('search', $this->tableA->name)
        ->call('setStatus', Order::STATUS_NEW)
        ->call('clearFilters')
        ->assertSet('dateFrom', '')->assertSet('dateTo', '')
        ->assertSet('search', '')->assertSet('status', 'all')
        ->assertSet('table', null)->assertHasNoErrors();
});

it('treats an oversized number or SQL-like text as a search term rather than an order id', function (string $term) {
    Order::factory()->create(['restaurant_table_id' => $this->tableA->id]);

    $component = Livewire::test('pages::orders')->set('search', $term);

    expect($component->get('orders')->total())->toBe(0);
})->with(['999999999999999999999999999999', "' OR 1=1 --"]);
