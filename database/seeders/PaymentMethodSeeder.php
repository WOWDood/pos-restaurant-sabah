<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['description' => 'Cash', 'sort_order' => 1, 'active' => true, 'ref_required' => false],
            ['description' => 'Credit Card', 'sort_order' => 2, 'active' => true, 'ref_required' => true],
            ['description' => 'A M Card', 'sort_order' => 3, 'active' => false, 'ref_required' => false],
            ['description' => 'E-wallet', 'sort_order' => 4, 'active' => true, 'ref_required' => false],
        ];
        foreach ($rows as $row) {
            PaymentMethod::updateOrCreate(['description' => $row['description']], $row);
        }
    }
}