<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RestaurantTable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Gate::denies('view-dashboard')) {
            return redirect()->route('profile');
        }
        $updatedAt = CarbonImmutable::now('Asia/Kuala_Lumpur');
        $today = $updatedAt->startOfDay();
        $storageTimezone = config('app.timezone');
        $start = $today->setTimezone($storageTimezone);
        $end = $today->addDay()->setTimezone($storageTimezone);
        $paidOrders = Order::query()->where('status', Order::STATUS_PAID);
        $dailyQuery = (clone $paidOrders)
            ->where('paid_at', '>=', $today->subDays(6)->setTimezone($storageTimezone))
            ->where('paid_at', '<', $end);

        for ($index = 0; $index < 7; $index++) {
            $day = $today->subDays(6 - $index);
            $bindings = [$day->setTimezone($storageTimezone), $day->addDay()->setTimezone($storageTimezone)];
            $dailyQuery
                ->selectRaw("COALESCE(SUM(CASE WHEN paid_at >= ? AND paid_at < ? THEN total ELSE 0 END), 0) as sales_{$index}", $bindings)
                ->selectRaw("COUNT(CASE WHEN paid_at >= ? AND paid_at < ? THEN 1 END) as orders_{$index}", $bindings);
        }

        $dailyTotals = $dailyQuery->toBase()->first();
        $salesDays = collect(range(0, 6))->map(function (int $index) use ($today, $dailyTotals): array {
            return [
                'date' => $today->subDays(6 - $index),
                'sales' => (float) $dailyTotals->{'sales_'.$index},
                'orders' => (int) $dailyTotals->{'orders_'.$index},
            ];
        });
        $todaySales = $salesDays->last()['sales'];
        $todayOrderCount = $salesDays->last()['orders'];
        $yesterdaySales = $salesDays[5]['sales'];
        $openQuery = Order::query()->whereIn('status', Order::OPEN_STATUSES);
        $openSummary = (clone $openQuery)->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as total')->toBase()->first();
        $totalTables = RestaurantTable::count();
        $occupiedTables = RestaurantTable::whereHas('orders', fn (Builder $query): Builder => $query->whereIn('status', Order::OPEN_STATUSES))->count();
        $todayPaidOrders = fn (Builder $query): Builder => $query->where('status', Order::STATUS_PAID)
            ->where('paid_at', '>=', $start)->where('paid_at', '<', $end);

        $paymentTotals = Payment::query()
            ->where('status', 'paid')
            ->whereHas('order', $todayPaidOrders)
            ->with('method:id,description')
            ->select('payment_method_id')
            ->selectRaw('SUM(amount) as collected')
            ->groupBy('payment_method_id')
            ->orderByDesc('collected')
            ->get();
        $topItems = OrderItem::query()
            ->whereHas('order', $todayPaidOrders)
            ->select('item_id', 'item_code', 'item_description')
            ->selectRaw('SUM(quantity) as sold_quantity')
            ->groupBy('item_id', 'item_code', 'item_description')
            ->orderByDesc('sold_quantity')
            ->orderBy('item_code')
            ->limit(4)
            ->get();

        return view('dashboard', [
            'settings' => BusinessSetting::pluck('value', 'key'),
            'updatedAt' => $updatedAt,
            'todaySales' => $todaySales,
            'todayOrderCount' => $todayOrderCount,
            'averageOrder' => $todayOrderCount > 0 ? round($todaySales / $todayOrderCount, 2) : 0,
            'yesterdaySales' => $yesterdaySales,
            'salesDifference' => round($todaySales - $yesterdaySales, 2),
            'salesDays' => $salesDays,
            'weekSales' => round($salesDays->sum('sales'), 2),
            'chartMaximum' => ceil(max(1, (float) $salesDays->max('sales')) / 100) * 100,
            'openOrderCount' => (int) $openSummary->order_count,
            'openOrderTotal' => (float) $openSummary->total,
            'totalTables' => $totalTables,
            'occupiedTables' => $occupiedTables,
            'availableTables' => $totalTables - $occupiedTables,
            'openOrders' => (clone $openQuery)->with('table:id,name')->oldest()->orderBy('id')->limit(5)->get(),
            'recentOrders' => Order::with('table:id,name')->latest('updated_at')->orderByDesc('id')->limit(6)->get(),
            'paymentTotals' => $paymentTotals,
            'paymentTotal' => round($paymentTotals->sum('collected'), 2),
            'topItems' => $topItems,
        ]);
    }
}
