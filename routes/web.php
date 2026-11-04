<?php

use App\Http\Controllers\CustomerOrderController;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/order/{table}', [CustomerOrderController::class, 'create'])->name('customer-order.create');
Route::get('/menu/photo/{photo}', [CustomerOrderController::class, 'photo'])->name('customer-order.photo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('admin/users', 'pages::manage-users')->middleware('can:manage-users')->name('admin.users');
    Route::get('dashboard', function () {
        $settings = BusinessSetting::pluck('value', 'key');

        return view('dashboard', compact('settings'));
    })->name('dashboard');

    Route::livewire('pos', 'pages::pos')->name('pos');
    Route::livewire('pos/order/{order}', 'pages::pos-order')->name('pos.order');
    Route::livewire('orders', 'pages::orders')->name('orders');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('profile', 'pages::profile')->name('profile');
    Route::livewire('setup/categories', 'pages::categories')->name('categories');
    Route::livewire('setup/items', 'pages::items')->name('items');
    Route::livewire('setup/tables', 'pages::tables')->name('tables');
    Route::livewire('setup/table-qr', 'pages::table-qr')->name('table-qr');
    Route::livewire('setup/payment-methods', 'pages::payment-methods')->name('payment-methods');
});

require __DIR__.'/settings.php';
