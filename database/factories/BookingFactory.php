<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hall = Hall::factory();
        $startHour = fake()->numberBetween(Hall::OPENS_AT, Hall::CLOSES_AT - Hall::HOURS_PER_BLOCK);
        $hours = Hall::HOURS_PER_BLOCK;
        $includeSkirting = fake()->boolean();

        return [
            'reference' => Booking::generateReference(),
            'hall_id' => $hall,
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_phone' => '09'.fake()->numerify('#########'),
            'guest_email' => fake()->unique()->safeEmail(),
            'booking_date' => fake()->dateTimeBetween('+1 day', '+2 months')->format('Y-m-d'),
            'start_hour' => $startHour,
            'end_hour' => $startHour + $hours,
            'hours' => $hours,
            'include_skirting' => $includeSkirting,
            'status' => BookingStatus::Pending,
        ];
    }

    /**
     * Fill the money columns from the hall's own pricing once it is resolved.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Booking $booking) {
            $quote = $booking->hall->quote($booking->hours, $booking->include_skirting);

            $booking->forceFill([
                'rent_total' => $quote['rent_total'],
                'skirting_total' => $quote['skirting_total'],
                'total' => $quote['total'],
                'downpayment' => $quote['downpayment'],
                'balance' => $quote['balance'],
            ]);
        });
    }

    /**
     * Indicate that the downpayment has been verified.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the booking was cancelled and no longer holds its slot.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
