<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $food = Category::where('code', 'Food')->first();

        $a1 = Item::updateOrCreate(['code' => 'A1'], [
            'category_id' => $food->id,
            'description' => 'Mee Sup Inanam',
            'unit_price' => 8.00,
            'active' => true,
            'type' => 'General',
            'is_combo' => false,
        ]);

        $foodGroup = ModifierGroup::updateOrCreate(['item_id' => $a1->id, 'name' => 'food'], [
            'min_select' => 2,
            'max_select' => 2,
            'sort_order' => 1,
        ]);
        foreach ([
            ['name_en' => 'cunkien', 'name_zh' => '春卷'],
            ['name_en' => 'wonton', 'name_zh' => '云吞'],
            ['name_en' => 'casau', 'name_zh' => '叉烧'],
        ] as $i => $opt) {
            ModifierOption::updateOrCreate(['modifier_group_id' => $foodGroup->id, 'name_en' => $opt['name_en']], [
                'name_zh' => $opt['name_zh'],
                'sort_order' => $i + 1,
            ]);
        }

        $meeGroup = ModifierGroup::updateOrCreate(['item_id' => $a1->id, 'name' => 'mee/hun'], [
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 2,
        ]);
        foreach ([
            ['name_en' => 'Mee Hong Kong', 'name_zh' => '香港面'],
            ['name_en' => 'Meehun', 'name_zh' => '米粉'],
            ['name_en' => 'Mee Tuaran', 'name_zh' => '斗亚兰面'],
            ['name_en' => 'Kuitiew', 'name_zh' => '粿条'],
        ] as $i => $opt) {
            ModifierOption::updateOrCreate(['modifier_group_id' => $meeGroup->id, 'name_en' => $opt['name_en']], [
                'name_zh' => $opt['name_zh'],
                'sort_order' => $i + 1,
            ]);
        }

        $setla = Item::updateOrCreate(['code' => 'setLA'], [
            'category_id' => $food->id,
            'description' => 'Set Lunch A',
            'unit_price' => 20.00,
            'active' => true,
            'type' => 'Combo',
            'is_combo' => true,
        ]);

        $drinkGroup = ModifierGroup::updateOrCreate(['item_id' => $setla->id, 'name' => 'Drink'], [
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 1,
        ]);
        foreach ([
            ['name_en' => 'D1 - Coffe', 'name_zh' => '咖啡'],
            ['name_en' => 'D3 - Teh C', 'name_zh' => null],
            ['name_en' => 'D4 - Chines teh', 'name_zh' => '中国茶'],
        ] as $i => $opt) {
            ModifierOption::updateOrCreate(['modifier_group_id' => $drinkGroup->id, 'name_en' => $opt['name_en']], [
                'name_zh' => $opt['name_zh'],
                'sort_order' => $i + 1,
            ]);
        }

        $mainGroup = ModifierGroup::updateOrCreate(['item_id' => $setla->id, 'name' => 'Main Dish'], [
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 2,
        ]);
        foreach ([
            ['name_en' => 'C1 - Nasi Goreng', 'name_zh' => '炒饭'],
            ['name_en' => 'C3 - Goreng Tuaran Mee', 'name_zh' => '斗亚兰炒面'],
        ] as $i => $opt) {
            ModifierOption::updateOrCreate(['modifier_group_id' => $mainGroup->id, 'name_en' => $opt['name_en']], [
                'name_zh' => $opt['name_zh'],
                'sort_order' => $i + 1,
            ]);
        }
    }
}