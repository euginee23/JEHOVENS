<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Notifications\ReservationHoldExpired;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Releasing abandoned checkouts
|--------------------------------------------------------------------------
|
| A booking is written before the guest is sent to PayMongo, which is what holds its dates
| while they pay. Most pay; the rest close the tab. Without the sweeper those dates would
| be held for ever by a booking nobody paid for.
|
*/

beforeEach(function () {
    Notification::fake();

    $this->hall = Hall::factory()->create();
});

/**
 * A booking left mid-checkout, with its hold already run out.
 */
function abandonedBooking(Hall $hall, array $overrides = []): Booking
{
    return Booking::factory()->for($hall)->create([
        'guest_email' => 'juan@example.com',
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Awaiting,
        'payment_expires_at' => now()->subMinute(),
        ...$overrides,
    ]);
}

test('an expired hold releases its dates', function () {
    $booking = abandonedBooking($this->hall);

    expect(Booking::query()->blocking()->count())->toBe(1);

    $this->artisan('resort:expire-unpaid-reservations')
        ->expectsOutputToContain('Released 1 unpaid booking(s).')
        ->assertSuccessful();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->payment_status)->toBe(PaymentStatus::Expired)
        // Cancelled does not block, so the dates are on sale again.
        ->and(Booking::query()->blocking()->count())->toBe(0);
});

test('the guest is told their dates have gone', function () {
    abandonedBooking($this->hall);

    $this->artisan('resort:expire-unpaid-reservations');

    Notification::assertSentOnDemand(ReservationHoldExpired::class);
});

test('a hold that has not run out yet is left alone', function () {
    $booking = abandonedBooking($this->hall, ['payment_expires_at' => now()->addHour()]);

    $this->artisan('resort:expire-unpaid-reservations')
        ->expectsOutputToContain('No unpaid bookings to release.');

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

test('a booking that was paid for is never released', function () {
    $booking = abandonedBooking($this->hall, [
        'payment_status' => PaymentStatus::Paid,
        'status' => BookingStatus::Confirmed,
    ]);

    $this->artisan('resort:expire-unpaid-reservations');

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    Notification::assertNothingSent();
});

// Bookings taken before PayMongo, and any taken by hand, have no hold to run out.
test('a booking with no hold recorded is left alone', function () {
    $booking = abandonedBooking($this->hall, ['payment_expires_at' => null]);

    $this->artisan('resort:expire-unpaid-reservations');

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

test('rooms and catering are swept too', function () {
    $room = Room::factory()->withRates([6 => 1200])->create();

    $stay = RoomBooking::factory()->for($room)->create([
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Awaiting,
        'payment_expires_at' => now()->subMinute(),
    ]);

    $order = CateringOrder::factory()->create([
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Awaiting,
        'payment_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('resort:expire-unpaid-reservations')
        ->expectsOutputToContain('Released 2 unpaid booking(s).');

    expect($stay->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($order->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('sweeping twice does not cancel anything twice', function () {
    abandonedBooking($this->hall);

    $this->artisan('resort:expire-unpaid-reservations');
    $this->artisan('resort:expire-unpaid-reservations')
        ->expectsOutputToContain('No unpaid bookings to release.');

    Notification::assertSentOnDemandTimes(ReservationHoldExpired::class, 1);
});

// The whole thing only runs if the scheduler does, so that is worth pinning down.
test('the sweeper is scheduled', function () {
    $events = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '');

    expect($events->contains(fn (string $command) => str_contains($command, 'resort:expire-unpaid-reservations')))->toBeTrue();
});
