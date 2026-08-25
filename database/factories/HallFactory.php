<?php

namespace Database\Factories;

use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hall>
 */
class HallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->lastName().' Hall';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'capacity' => fake()->numberBetween(50, 500),
            'rent_price' => fake()->numberBetween(3, 10) * 1000,
            'skirting_price' => fake()->numberBetween(2, 5) * 1000,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the hall is not currently bookable.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
