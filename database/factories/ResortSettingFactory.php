<?php

namespace Database\Factories;

use App\Models\ResortSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResortSetting>
 */
class ResortSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => '0917 '.fake()->numerify('### ####'),
        ];
    }
}
