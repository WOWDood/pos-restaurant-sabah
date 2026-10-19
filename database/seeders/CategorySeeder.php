<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'Drink', 'description' => 'Drink', 'sort_order' => 1],
            ['code' => 'Food', 'description' => 'Food', 'sort_order' => 2],
        ] as $data) {
            Category::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}