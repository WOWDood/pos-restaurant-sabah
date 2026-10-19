<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $food = Category::where('code', 'Food')->first();
        $drink = Category::where('code', 'Drink')->first();

        Item::updateOrCreate(['code' => 'A1'], [
            'category_id' => $food->id,
            'description' => 'Mee Sup Inanam',
            'unit_price' => 8.00,
            'active' => true,
            'type' => 'General',
            'is_combo' => false,
        ]);

        Item::updateOrCreate(['code' => 'setLA'], [
            'category_id' => $food->id,
            'description' => 'Set Lunch A',
            'unit_price' => 20.00,
            'active' => true,
            'type' => 'Combo',
            'is_combo' => true,
        ]);
    }
}