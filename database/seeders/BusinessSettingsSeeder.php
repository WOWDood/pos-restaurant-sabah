<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'business_name' => 'Kedai Soto Banso',
            'location' => 'Tuaran',
            'phone' => '012-8261800',
        ];

        foreach ($settings as $key => $value) {
            DB::table('business_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}