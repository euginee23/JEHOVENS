<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Support\PhotoStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
                'photo' => 'room-1.jpg',
                'rates' => [6 => 1200, 12 => 1800, 24 => 2500],
            ],
            [
                'name' => 'Standard Room 102',
                'slug' => 'standard-room-102',
                'description' => 'Air-conditioned room for two with a queen bed, private bath, and garden view.',
                'sort_order' => 2,
                'photo' => 'room-2.jpg',
                'rates' => [6 => 1200, 12 => 1800, 24 => 2500],
            ],
            [
                'name' => 'Family Room 201',
                'slug' => 'family-room-201',
                'description' => 'Sleeps four with two double beds, air-conditioning, private bath, and pool view.',
                'sort_order' => 3,
                'photo' => 'room-3.jpg',
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
            $photo = $data['photo'] ?? null;
            unset($data['rates'], $data['photo']);

            $room = Room::updateOrCreate(['slug' => $data['slug']], $data);

            foreach ($rates as $hours => $price) {
                $room->rates()->updateOrCreate(['hours' => $hours], ['price' => $price]);
            }

            $this->attachPhoto($room, $photo);
        }
    }

    /**
     * Copy one of the bundled room photos onto the public disk, so a fresh install has
     * something to show before anyone uploads anything.
     */
    private function attachPhoto(Room $room, ?string $photo): void
    {
        if ($photo === null || $room->photos()->exists()) {
            return;
        }

        $source = public_path('images/rooms/'.$photo);

        if (! is_file($source)) {
            return;
        }

        $path = 'rooms/seed-'.$room->slug.'.jpg';

        Storage::disk(PhotoStore::DISK)->put($path, (string) file_get_contents($source));

        $room->photos()->create([
            'path' => $path,
            'alt' => __(':room at Jehoven\'s Garden Resort', ['room' => $room->name]),
            'sort_order' => 1,
        ]);
    }
}
