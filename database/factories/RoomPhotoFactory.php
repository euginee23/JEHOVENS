<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomPhoto>
 */
class RoomPhotoFactory extends Factory
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
            'path' => 'rooms/'.Str::uuid()->toString().'.jpg',
            'alt' => fake()->sentence(4),
            'sort_order' => 0,
        ];
    }
}
