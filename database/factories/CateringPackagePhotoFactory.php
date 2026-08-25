<?php

namespace Database\Factories;

use App\Models\CateringPackage;
use App\Models\CateringPackagePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CateringPackagePhoto>
 */
class CateringPackagePhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catering_package_id' => CateringPackage::factory(),
            'path' => 'cateringpackages/'.Str::uuid()->toString().'.jpg',
            'alt' => fake()->sentence(4),
            'sort_order' => 0,
        ];
    }
}
