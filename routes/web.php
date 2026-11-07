<?php

use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ShopLogoController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('shop/logo', ShopLogoController::class)->name('shop.logo');

Route::get('/order/{table}', [CustomerOrderController::class, 'create'])->name('customer-order.create');
Route::get('/menu/photo/{photo}', [CustomerOrderController::class, 'photo'])->name('customer-order.photo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('admin/users', '/setup/shop-settings/users')->middleware('can:manage-users');
    Route::livewire('setup/shop-settings', 'pages::shop-settings')->middleware('can:manage-shop')->name('admin.shop-settings');
    Route::livewire('setup/shop-settings/roles', 'pages::staff-roles')->middleware('can:manage-users')->name('admin.staff-roles');
    Route::livewire('setup/shop-settings/users', 'pages::manage-users')->middleware('can:manage-users')->name('admin.users');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::livewire('pos', 'pages::pos')->middleware('can:use-pos')->name('pos');
    Route::livewire('pos/order/{order}', 'pages::pos-order')->middleware('can:use-pos')->name('pos.order');
    Route::livewire('orders', 'pages::orders')->middleware('can:view-orders')->name('orders');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('profile', 'pages::profile')->name('profile');
    Route::livewire('setup/categories', 'pages::categories')->middleware('can:manage-menu')->name('categories');
    Route::livewire('setup/items', 'pages::items')->middleware('can:manage-menu')->name('items');
    Route::livewire('setup/tables', 'pages::tables')->middleware('can:manage-tables')->name('tables');
    Route::livewire('setup/table-qr', 'pages::table-qr')->middleware('can:manage-tables')->name('table-qr');
    Route::livewire('setup/payment-methods', 'pages::payment-methods')->middleware('can:manage-payment-methods')->name('payment-methods');
});

require __DIR__.'/settings.php';
