<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            RestaurantTable::updateOrCreate(['name' => 'table' . str_pad($i, 2, '0', STR_PAD_LEFT)]);
        }
        RestaurantTable::updateOrCreate(['name' => 'Table11']);
        RestaurantTable::updateOrCreate(['name' => 'Walk in']);
    }
}