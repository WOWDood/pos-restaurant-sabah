<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'item_code' => $this->faker->unique()->bothify('ITEM-####'),
            'item_description' => $this->faker->words(2, true),
            'quantity' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
        ];
    }
}
