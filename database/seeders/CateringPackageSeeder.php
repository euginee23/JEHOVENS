<?php

namespace Database\Seeders;

use App\Models\CateringPackage;
use App\Support\PhotoStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CateringPackageSeeder extends Seeder
{
    /**
     * Seed the resort's catering packages.
     *
     * Names, descriptions, and prices come from the design mockup. The prices are
     * per head — swap them for the resort's real rate card.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Mediterranean Mezze',
                'slug' => 'mediterranean-mezze',
                'description' => 'Hummus, baba ganoush, fresh pita, olives & roasted peppers.',
                'price_per_head' => 450,
                'skirting_price' => 5000,
                'minimum_guests' => 20,
                'sort_order' => 1,
            ],
            [
                'name' => 'Truffle Risotto',
                'slug' => 'truffle-risotto',
                'description' => 'Creamy arborio rice, wild mushrooms, parmesan & truffle oil.',
                'price_per_head' => 550,
                'skirting_price' => 5000,
                'minimum_guests' => 20,
                'sort_order' => 2,
            ],
            [
                'name' => 'Seared Salmon Bowl',
                'slug' => 'seared-salmon-bowl',
                'description' => 'Citrus glaze, avocado, sesame, quinoa & fresh herbs.',
                'price_per_head' => 650,
                'skirting_price' => 5000,
                'minimum_guests' => 20,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            CateringPackage::updateOrCreate(['slug' => $package['slug']], $package);
        }

        $this->attachSeedPhoto();
    }

    /**
     * Give the first record the bundled photo, so a fresh install is not empty.
     */
    private function attachSeedPhoto(): void
    {
        $record = CateringPackage::query()->orderBy('sort_order')->first();
        $source = public_path('images/catering/catering-1.jpg');

        if (! $record || $record->photos()->exists() || ! is_file($source)) {
            return;
        }

        $path = 'catering/seed-'.$record->slug.'.jpg';

        Storage::disk(PhotoStore::DISK)->put($path, (string) file_get_contents($source));

        $record->photos()->create([
            'path' => $path,
            'alt' => __(':name at Jehoven\'s Garden Resort', ['name' => $record->name]),
            'sort_order' => 1,
        ]);
    }
}
