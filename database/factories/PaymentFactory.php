<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => Booking::class,
            'payable_id' => Booking::factory()->state(['status' => BookingStatus::Confirmed]),
            'kind' => PaymentKind::Downpayment,
            'amount' => fake()->numberBetween(1, 20) * 500,
            'method' => fake()->randomElement(['gcash', 'card', 'qrph']),
            'reference' => 'pay_'.fake()->bothify('????????????'),
            'recorded_by' => null,
            'received_at' => now(),
        ];
    }

    /**
     * Indicate that the payment settled the remaining balance.
     */
    public function balance(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => PaymentKind::Balance,
        ]);
    }
}
