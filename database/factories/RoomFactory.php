<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Room '.fake()->unique()->numberBetween(101, 999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the room is not currently bookable.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Give the room a standard 6 / 12 / 24-hour rate card.
     *
     * @param  array<int, int>  $rates  hours => price
     */
    public function withRates(array $rates = [6 => 1200, 12 => 1800, 24 => 2500]): static
    {
        return $this->afterCreating(function (Room $room) use ($rates) {
            foreach ($rates as $hours => $price) {
                $room->rates()->create(['hours' => $hours, 'price' => $price]);
            }
        });
    }
}
