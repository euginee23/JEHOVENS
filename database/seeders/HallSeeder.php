<?php

namespace Database\Seeders;

use App\Models\Hall;
use Illuminate\Database\Seeder;

class HallSeeder extends Seeder
{
    /**
     * Seed the resort's function halls.
     */
    public function run(): void
    {
        $halls = [
            [
                'name' => 'Grand Ballroom',
                'slug' => 'grand-ballroom',
                'description' => 'Spacious 500-person capacity, crystal chandeliers, stage area, premium sound system, and elegant decor.',
                'capacity' => 500,
                'rent_price' => 8000,
                'skirting_price' => 5000,
                'sort_order' => 1,
            ],
            [
                'name' => 'Garden Pavilion',
                'slug' => 'garden-pavilion',
                'description' => 'Open-air venue with garden views, capacity for 200 guests, string lights, and natural ventilation.',
                'capacity' => 200,
                'rent_price' => 5000,
                'skirting_price' => 3000,
                'sort_order' => 2,
            ],
            [
                'name' => 'Executive Hall',
                'slug' => 'executive-hall',
                'description' => 'Intimate setting for 80 guests, perfect for corporate meetings, seminars, and private dinners.',
                'capacity' => 80,
                'rent_price' => 3000,
                'skirting_price' => 2000,
                'sort_order' => 3,
            ],
        ];

        foreach ($halls as $hall) {
            Hall::updateOrCreate(['slug' => $hall['slug']], $hall);
        }
    }
}
