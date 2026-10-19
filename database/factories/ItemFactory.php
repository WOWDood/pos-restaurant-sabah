<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('??#'),
            'category_id' => \App\Models\Category::factory(),
            'description' => $this->faker->words(2, true),
            'unit_price' => $this->faker->randomFloat(2, 1, 20),
            'active' => true,
        ];
    }
}
