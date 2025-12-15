<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement(['BTC', 'ETH']),
            'side' => fake()->randomElement(['buy', 'sell']),
            'price' => fake()->randomFloat(8, 0, 1000000),
            'amount' => fake()->randomFloat(8, 0, 1000000),
            'status' => fake()->randomElement([1, 2, 3]),
        ];
    }
}
