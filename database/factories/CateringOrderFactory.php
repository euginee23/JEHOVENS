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
            'event_date' => fake()->dateTimeBetween('+1 day', '+3 months')->format('Y-m-d'),
            'guests' => fake()->numberBetween(20, 200),
            'include_skirting' => fake()->boolean(),
            'status' => BookingStatus::Pending,
        ];
    }

    /**
     * Fill the money columns from the package's own pricing once it is resolved.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (CateringOrder $order) {
            $quote = $order->package->quote($order->guests, $order->include_skirting);

            $order->forceFill([
                'price_per_head' => $order->package->price_per_head,
                ...$quote,
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
     * Indicate that the order was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
        ]);
    }
}
