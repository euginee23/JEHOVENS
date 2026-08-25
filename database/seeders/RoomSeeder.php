<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Seed the resort's rooms and their rate cards.
     *
     * These are placeholders so the booking page is usable — swap the names,
     * descriptions, and prices for the resort's real inventory.
     */
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Standard Room 101',
                'slug' => 'standard-room-101',
                'description' => 'Air-conditioned room for two with a queen bed, private bath, and pool access.',
                'sort_order' => 1,
                'rates' => [6 => 1200, 12 => 1800, 24 => 2500],
            ],
            [
                'name' => 'Standard Room 102',
                'slug' => 'standard-room-102',
                'description' => 'Air-conditioned room for two with a queen bed, private bath, and garden view.',
                'sort_order' => 2,
                'rates' => [6 => 1200, 12 => 1800, 24 => 2500],
            ],
            [
                'name' => 'Family Room 201',
                'slug' => 'family-room-201',
                'description' => 'Sleeps four with two double beds, air-conditioning, private bath, and pool view.',
                'sort_order' => 3,
                'rates' => [6 => 2000, 12 => 2800, 24 => 3800],
            ],
            [
                'name' => 'Cabana Suite 301',
                'slug' => 'cabana-suite-301',
                'description' => 'Our largest suite — sleeps six, with a lounge area, kitchenette, and private veranda.',
                'sort_order' => 4,
                'rates' => [6 => 3000, 12 => 4200, 24 => 5500],
            ],
        ];

        foreach ($rooms as $data) {
            $rates = $data['rates'];
            unset($data['rates']);

            $room = Room::updateOrCreate(['slug' => $data['slug']], $data);

            foreach ($rates as $hours => $price) {
                $room->rates()->updateOrCreate(['hours' => $hours], ['price' => $price]);
            }
        }
    }
}
