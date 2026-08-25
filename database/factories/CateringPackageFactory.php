<?php

namespace Database\Factories;

use App\Models\CateringPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CateringPackage>
 */
class CateringPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->lastName().' Platter');

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(8),
            'price_per_head' => fake()->numberBetween(3, 10) * 50,
            'skirting_price' => 5000,
            'minimum_guests' => 20,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the package is not currently orderable.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
