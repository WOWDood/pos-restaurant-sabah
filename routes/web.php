<?php

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $settings = BusinessSetting::pluck('value', 'key');

        return view('dashboard', compact('settings'));
    })->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('profile', 'pages::profile')->name('profile');
    Route::livewire('setup/categories', 'pages::categories')->name('categories');
    Route::livewire('setup/items', 'pages::items')->name('items');
});

require __DIR__.'/settings.php';
