<?php

namespace Database\Seeders;

use App\Models\CateringPackage;
use App\Models\Hall;
use App\Models\Room;
use App\Support\PayMongo;
use Illuminate\Database\Seeder;

/**
 * Cheap inventory for testing a real payment end to end.
 *
 * Deliberately NOT registered in {@see DatabaseSeeder} — run it by hand:
 *
 *     php artisan db:seed --class=PaymentTestSeeder
 *
 * Prices are as low as PayMongo will allow, not as low as possible. The gateway refuses
 * anything under ₱20, and the resort takes a 50% downpayment, so a ₱1 room would be
 * rejected before a request was ever sent. Everything here is priced so the amount
 * actually charged clears that floor with a little room to spare.
 *
 * Take these out of the public booking pages before going live — see `deactivate()`.
 */
class PaymentTestSeeder extends Seeder
{
    /**
     * The smallest total worth seeding: half of it still clears the gateway's floor.
     */
    private const TEST_TOTAL = 50;

    /**
     * Marks the records this seeder owns, so they can be found and switched off again.
     */
    private const PREFIX = 'TEST — ';

    /**
     * Seed one hall, one room and one catering package that cost almost nothing.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('PaymentTestSeeder refuses to run in production.');

            return;
        }

        $this->seedHall();
        $this->seedRoom();
        $this->seedCateringPackage();

        $this->report();
    }

    /**
     * A hall renting for ₱50 per four-hour block, so the minimum booking pays ₱25.
     */
    private function seedHall(): void
    {
        Hall::updateOrCreate(['slug' => 'test-payment-hall'], [
            'name' => self::PREFIX.'Function Hall',
            'description' => 'Test inventory for checking payments end to end. Not a real venue.',
            'capacity' => 10,
            'rent_price' => self::TEST_TOTAL,
            // Zero, so the total is exactly the rent and the arithmetic is easy to follow.
            'skirting_price' => 0,
            'is_active' => true,
            'sort_order' => 99,
        ]);
    }

    /**
     * A room whose cheapest day-use block is ₱50 and whose nightly rate is ₱60.
     *
     * Both are seeded so day use and an overnight stay can each be paid for — they take
     * different paths through the booking form.
     */
    private function seedRoom(): void
    {
        $room = Room::updateOrCreate(['slug' => 'test-payment-room'], [
            'name' => self::PREFIX.'Room',
            'description' => 'Test inventory for checking payments end to end. Not a real room.',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        foreach ([6 => self::TEST_TOTAL, 24 => 60] as $hours => $price) {
            $room->rates()->updateOrCreate(['hours' => $hours], ['price' => $price]);
        }
    }

    /**
     * A package at ₱50 a head with a minimum of one guest, so one guest pays ₱25.
     */
    private function seedCateringPackage(): void
    {
        CateringPackage::updateOrCreate(['slug' => 'test-payment-catering'], [
            'name' => self::PREFIX.'Catering',
            'description' => 'Test inventory for checking payments end to end. Not a real package.',
            'price_per_head' => self::TEST_TOTAL,
            'skirting_price' => 0,
            'minimum_guests' => 1,
            'is_active' => true,
            'sort_order' => 99,
        ]);
    }

    /**
     * Say what was seeded and what each one will actually charge.
     */
    private function report(): void
    {
        $downpayment = (int) ceil(self::TEST_TOTAL * Hall::DOWNPAYMENT_RATE);

        $this->command->info('Seeded test inventory. PayMongo will not take under ₱'.PayMongo::MINIMUM_PESOS.'.');
        $this->command->table(
            ['What', 'Cheapest booking', 'Charged now'],
            [
                [self::PREFIX.'Function Hall', '4 hours — ₱'.self::TEST_TOTAL, '₱'.$downpayment],
                [self::PREFIX.'Room (day use)', '6 hours — ₱'.self::TEST_TOTAL, '₱'.$downpayment],
                [self::PREFIX.'Room (overnight)', '1 night — ₱60', '₱30'],
                [self::PREFIX.'Catering', '1 guest — ₱'.self::TEST_TOTAL, '₱'.$downpayment],
            ],
        );
    }

    /**
     * Hide the test inventory from the public booking pages.
     *
     *     php artisan tinker --execute 'Database\Seeders\PaymentTestSeeder::deactivate();'
     *
     * Deactivated rather than deleted: once anything has been booked against them the
     * foreign keys refuse a delete, on purpose — payment history has to survive.
     */
    public static function deactivate(): void
    {
        Hall::where('slug', 'test-payment-hall')->update(['is_active' => false]);
        Room::where('slug', 'test-payment-room')->update(['is_active' => false]);
        CateringPackage::where('slug', 'test-payment-catering')->update(['is_active' => false]);
    }
}
