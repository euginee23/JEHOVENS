<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CateringOrder>
 */
class CateringOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => CateringOrder::generateReference(),
            'catering_package_id' => CateringPackage::factory(),
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_phone' => '09'.fake()->numerify('#########'),
            'guest_email' => fake()->unique()->safeEmail(),
            'start_date' => fake()->dateTimeBetween('+1 day', '+3 months')->format('Y-m-d'),

            // Left for configure() to work out from `days`, so a test overriding only
            // `start_date` still gets a coherent range.
            'end_date' => null,
            'days' => 1,
            'guests' => fake()->numberBetween(20, 200),
            'include_skirting' => fake()->boolean(),
            'status' => BookingStatus::Pending,
        ];
    }

    /**
     * Settle the date range, then fill the money columns from the package's own pricing.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CateringOrder $order) {
            // Both ends are settled here rather than in a state, so an explicit
            // `start_date` passed to create() is the one the range is measured from.
            $order->end_date ??= $order->start_date->copy()->addDays(max($order->days, 1) - 1);
            $order->days = (int) $order->start_date->diffInDays($order->end_date) + 1;

            $quote = $order->package->quote($order->guests, $order->include_skirting, $order->days);

            $order->forceFill([
                'price_per_head' => $order->package->price_per_head,
                ...$quote,
            ]);
        });
    }

    /**
     * Indicate that the order covers several consecutive days.
     */
    public function spanningDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'days' => $days,
            'end_date' => null,
        ]);
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
     * Indicate that the order was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
