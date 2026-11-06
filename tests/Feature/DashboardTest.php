<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertDontSee('Test Drawing');
});

test('daily sales use Malaysian payment dates and exclude unpaid and cancelled orders', function () {
    $this->travelTo(Carbon::parse('2026-11-06 15:59:59', 'UTC'));
    $user = User::factory()->create();
    foreach ([
        [Order::STATUS_PAID, '2026-11-05 16:00:00', 12.50],
        [Order::STATUS_PAID, '2026-11-06 15:59:59', 7.50],
        [Order::STATUS_PAID, '2026-11-05 15:59:59', 30],
        [Order::STATUS_PAID, '2026-10-30 16:00:00', 5],
        [Order::STATUS_PAID, '2026-10-30 15:59:59', 900],
        [Order::STATUS_PAID, '2026-11-06 16:00:00', 800],
        [Order::STATUS_PAID, null, 700],
        [Order::STATUS_NEW, null, 100],
        [Order::STATUS_CANCELLED, '2026-11-05 16:00:00', 600],
    ] as [$status, $paidAt, $total]) {
        Order::factory()->create(['status' => $status, 'paid_at' => $paidAt, 'total' => $total, 'created_at' => '2026-10-29 08:00:00']);
    }

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('todaySales', 20.0)
        ->assertViewHas('todayOrderCount', 2)
        ->assertViewHas('averageOrder', 10.0)
        ->assertViewHas('yesterdaySales', 30.0)
        ->assertViewHas('salesDifference', -10.0)
        ->assertViewHas('weekSales', 55.0)
        ->assertViewHas('salesDays', fn ($days) => $days->pluck('sales')->all() === [5.0, 0.0, 0.0, 0.0, 0.0, 30.0, 20.0])
        ->assertSee('Daily paid sales in Malaysian ringgit')
        ->assertSee('RM10.00 below yesterday')
        ->assertSee('Malaysia time');
});

test('open bills include all dates and count occupied tables once', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $occupied = RestaurantTable::factory()->create();
    $available = RestaurantTable::factory()->count(2)->create();
    $oldest = Order::factory()->create(['restaurant_table_id' => $occupied->id, 'status' => Order::STATUS_NEW, 'total' => 10, 'created_at' => now()->subDays(2)]);
    Order::factory()->create(['restaurant_table_id' => $occupied->id, 'status' => Order::STATUS_PRE_ORDER, 'total' => 20]);
    $unassigned = Order::factory()->create(['status' => Order::STATUS_NEW, 'total' => 30]);
    Order::factory()->create(['restaurant_table_id' => $available[0]->id, 'status' => Order::STATUS_PAID, 'total' => 100, 'paid_at' => now()]);
    Order::factory()->create(['restaurant_table_id' => $available[1]->id, 'status' => Order::STATUS_CANCELLED, 'total' => 200]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('openOrderCount', 3)
        ->assertViewHas('openOrderTotal', 60.0)
        ->assertViewHas('occupiedTables', 1)
        ->assertViewHas('availableTables', 2)
        ->assertViewHas('totalTables', 3)
        ->assertViewHas('openOrders', fn ($orders) => $orders->first()->is($oldest) && $orders->contains('id', $unassigned->id))
        ->assertSee('No table assigned')
        ->assertSee(route('pos.order', $oldest), false);
});

test('payment methods and popular items only include todays paid orders', function () {
    $this->travelTo(Carbon::parse('2026-11-06 04:00:00', 'UTC'));
    $user = User::factory()->create();
    $cash = PaymentMethod::factory()->create(['description' => 'Cash', 'active' => false]);
    $paid = Order::factory()->create(['status' => Order::STATUS_PAID, 'total' => 35, 'paid_at' => now()]);
    $yesterday = Order::factory()->create(['status' => Order::STATUS_PAID, 'total' => 500, 'paid_at' => now()->subDay()]);
    $unpaid = Order::factory()->create(['status' => Order::STATUS_NEW, 'total' => 600]);
    foreach ([[$paid, 'paid', 30, $cash->id], [$paid, 'paid', 5, null], [$paid, 'failed', 900, $cash->id], [$yesterday, 'paid', 500, $cash->id], [$unpaid, 'paid', 600, $cash->id]] as [$order, $status, $amount, $methodId]) {
        Payment::factory()->create(['order_id' => $order->id, 'payment_method_id' => $methodId, 'status' => $status, 'amount' => $amount, 'paid_at' => now()]);
    }
    foreach ([[$paid, 'SOTO', 'Soto', 2], [$paid, 'SOTO', 'Soto', 3], [$paid, 'TEA', 'Tea', 1], [$yesterday, 'OLD', 'Yesterday item', 99], [$unpaid, 'UNPAID', 'Unpaid item', 100]] as [$order, $code, $description, $quantity]) {
        OrderItem::factory()->create(['order_id' => $order->id, 'item_code' => $code, 'item_description' => $description, 'quantity' => $quantity, 'unit_price' => 1, 'subtotal' => $quantity]);
    }

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('paymentTotal', 35.0)
        ->assertViewHas('paymentTotals', fn ($totals) => $totals->count() === 2 && (float) $totals->first()->collected === 30.0)
        ->assertViewHas('topItems', fn ($items) => $items->pluck('item_description')->all() === ['Soto', 'Tea'] && (int) $items->first()->sold_quantity === 5)
        ->assertSee('Cash')
        ->assertSee('Other payment')
        ->assertDontSee('Yesterday item')
        ->assertDontSee('Unpaid item');
});

test('empty dashboard has zero totals and useful next actions', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('todaySales', 0.0)
        ->assertViewHas('averageOrder', 0)
        ->assertViewHas('openOrderTotal', 0.0)
        ->assertViewHas('availableTables', 0)
        ->assertViewHas('weekSales', 0.0)
        ->assertSee('Set up tables')
        ->assertSee('Your first order starts here')
        ->assertDontSee('Daily paid sales in Malaysian ringgit')
        ->assertSee('No paid sales in this period');
});

test('dashboard lists are bounded with oldest open bills and latest updated orders first', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $orders = collect(range(1, 7))->map(fn (int $day): Order => Order::factory()->create([
        'status' => Order::STATUS_NEW,
        'total' => 1,
        'created_at' => now()->subDays($day),
        'updated_at' => now()->subHours($day),
    ]));

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('openOrderCount', 7)
        ->assertViewHas('openOrders', fn ($open) => $open->pluck('id')->all() === $orders->reverse()->take(5)->pluck('id')->all())
        ->assertViewHas('recentOrders', fn ($recent) => $recent->pluck('id')->all() === $orders->take(6)->pluck('id')->all())
        ->assertSee('Showing 5 of 7 open bills.');
});
