<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\RoomBooking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<RoomBooking>
 */
class RoomBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hours = 6;
        $startsAt = Carbon::parse(fake()->dateTimeBetween('+1 day', '+2 months')->format('Y-m-d'))
            ->setTime(fake()->numberBetween(Room::ENTRY_OPENS_AT, Room::ENTRY_CLOSES_AT), 0);
        $total = 1200;

        return [
            'reference' => RoomBooking::generateReference(),
            'room_id' => Room::factory(),
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_phone' => '09'.fake()->numerify('#########'),
            'guest_email' => fake()->unique()->safeEmail(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours($hours),
            'hours' => $hours,
            'pay_in_full' => false,
            'total' => $total,
            'amount_paid' => (int) ceil($total * Room::DOWNPAYMENT_RATE),
            'balance' => $total - (int) ceil($total * Room::DOWNPAYMENT_RATE),
            'status' => BookingStatus::Pending,
        ];
    }

    /**
     * Indicate that the payment has been verified.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the booking was cancelled and no longer holds the room.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
