<?php

namespace Database\Factories;

use App\Models\Hall;
use App\Models\HallPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HallPhoto>
 */
class HallPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hall_id' => Hall::factory(),
            'path' => 'halls/'.Str::uuid()->toString().'.jpg',
            'alt' => fake()->sentence(4),
            'sort_order' => 0,
        ];
    }
}
