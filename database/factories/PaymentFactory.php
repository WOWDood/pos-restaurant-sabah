<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->state(['status' => Order::STATUS_PAID, 'total' => 10, 'paid_at' => now()]),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => 10,
            'status' => 'paid',
            'paid_at' => now(),
        ];
    }
}
