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
     * What overnight() charges per night, matching the 24-hour rate RoomFactory seeds.
     */
    private const NIGHTLY_PRICE = 2500;

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

            // Zero nights is a day-use booking; overnight() sets this and the columns
            // that follow from it.
            'nights' => 0,
            'pay_in_full' => false,
            'total' => $total,
            'amount_paid' => (int) ceil($total * Room::DOWNPAYMENT_RATE),
            'balance' => $total - (int) ceil($total * Room::DOWNPAYMENT_RATE),
            'status' => BookingStatus::Pending,
        ];
    }

    /**
     * Indicate that the guest is staying the night rather than booking by the hour.
     *
     * The derived columns are filled in afterMaking so an explicit `starts_at` passed to
     * create() is the one the stay is measured from.
     */
    public function overnight(int $nights = 2): static
    {
        return $this->state(['nights' => $nights])
            ->afterMaking(function (RoomBooking $booking) use ($nights) {
                $total = self::NIGHTLY_PRICE * $nights;
                $paid = $booking->pay_in_full ? $total : (int) ceil($total * Room::DOWNPAYMENT_RATE);

                $booking->forceFill([
                    'hours' => $nights * Room::HOURS_PER_NIGHT,
                    'ends_at' => $booking->starts_at->copy()->addDays($nights),
                    'total' => $total,
                    'amount_paid' => $paid,
                    'balance' => $total - $paid,
                ]);
            });
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
