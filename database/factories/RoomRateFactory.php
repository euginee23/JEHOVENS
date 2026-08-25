<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomRate>
 */
class RoomRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'hours' => fake()->randomElement([3, 6, 12, 24]),
            'price' => fake()->numberBetween(1, 6) * 500,
        ];
    }
}
