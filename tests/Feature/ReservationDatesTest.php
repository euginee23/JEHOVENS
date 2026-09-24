<?php

use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Room;
use App\Models\RoomBooking;

/*
|--------------------------------------------------------------------------
| The days a reservation covers
|--------------------------------------------------------------------------
|
| `start_date` and `end_date` used to mean every day between them was booked. They are now
| only the outer bounds, and the days themselves are rows — which is what lets a guest book
| the 9th and the 20th without paying for the ten days in between.
|
*/

test('a reservation built by the factory lists every day it covers', function () {
    $booking = Booking::factory()->spanningDays(3)->create(['start_date' => '2027-04-10']);

    expect($booking->dateList())->toBe(['2027-04-10', '2027-04-11', '2027-04-12'])
        ->and($booking->days)->toBe(3);
});

test('picking days with gaps counts the days sold, not the span', function () {
    $booking = Booking::factory()->create();

    $booking->syncDates(['2027-04-09', '2027-04-15', '2027-04-20']);

    expect($booking->dateList())->toBe(['2027-04-09', '2027-04-15', '2027-04-20'])
        ->and($booking->days)->toBe(3)
        ->and($booking->start_date->toDateString())->toBe('2027-04-09')
        ->and($booking->end_date->toDateString())->toBe('2027-04-20')
        ->and($booking->isContiguous())->toBeFalse();
});

test('days handed over unsorted or repeated are stored once and in order', function () {
    $booking = Booking::factory()->create();

    $booking->syncDates(['2027-04-20', '2027-04-09', '2027-04-20']);

    expect($booking->dateList())->toBe(['2027-04-09', '2027-04-20'])
        ->and($booking->days)->toBe(2);
});

test('syncing again replaces the days rather than adding to them', function () {
    $booking = Booking::factory()->spanningDays(3)->create(['start_date' => '2027-04-10']);

    $booking->syncDates(['2027-05-01']);

    expect($booking->dateList())->toBe(['2027-05-01'])
        ->and($booking->days)->toBe(1)
        ->and($booking->dates()->count())->toBe(1);
});

test('a reservation cannot be left covering no days at all', function () {
    $booking = Booking::factory()->create();

    expect(fn () => $booking->syncDates([]))->toThrow(InvalidArgumentException::class);
});

test('deleting a reservation takes its days with it', function () {
    $booking = Booking::factory()->spanningDays(3)->create();

    expect(DB::table('booking_dates')->count())->toBe(3);

    $booking->delete();

    expect(DB::table('booking_dates')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Rooms
|--------------------------------------------------------------------------
*/

// The nights slept, not the span: a stay ending on the morning of the 13th leaves the room
// free to be sold that day.
test('an overnight stay holds its nights and not its checkout day', function () {
    $room = Room::factory()->withRates([24 => 2500])->create();

    $booking = RoomBooking::factory()->for($room)->overnight(3)->create(['starts_at' => '2027-05-10 14:00:00']);

    expect($booking->dateList())->toBe(['2027-05-10', '2027-05-11', '2027-05-12'])
        ->and($booking->days)->toBe(3)
        ->and($booking->ends_at->toDateString())->toBe('2027-05-13');
});

test('a day use holds the one day it runs on', function () {
    $booking = RoomBooking::factory()->create(['starts_at' => '2027-05-10 09:00:00']);

    expect($booking->dateList())->toBe(['2027-05-10'])
        ->and($booking->days)->toBe(1)
        ->and($booking->nights)->toBe(0);
});

// Rooms bound their stay with datetimes, so syncing days must leave those alone.
test('syncing a stay days does not disturb its check-in and check-out times', function () {
    $booking = RoomBooking::factory()->create(['starts_at' => '2027-05-10 09:00:00']);
    $startsAt = $booking->starts_at;
    $endsAt = $booking->ends_at;

    $booking->syncDates(['2027-05-10', '2027-05-17']);

    expect($booking->days)->toBe(2)
        ->and($booking->starts_at->eq($startsAt))->toBeTrue()
        ->and($booking->ends_at->eq($endsAt))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Whether the reservation is over
|--------------------------------------------------------------------------
|
| Marking a booking completed frees its days again, so "has this finished" has to be the
| last day it covers, not the span it happens to sit inside.
|
*/

// A hall knows the hour it closes, so "finished" is that hour on the last day — not the
// last day itself, which would free the hall while a morning event was still running.
test('a hall booking is finished once its closing hour on the last day has passed', function () {
    $booking = Booking::factory()->create(['start_hour' => 8, 'end_hour' => 12]);
    $booking->syncDates([today()->subWeek()->toDateString(), today()->toDateString()]);

    $this->travelTo(today()->setTime(11, 0));
    expect($booking->hasFinished())->toBeFalse();

    $this->travelTo(today()->setTime(13, 0));
    expect($booking->hasFinished())->toBeTrue();
});

test('a reservation with a day still to come is not finished', function () {
    $booking = Booking::factory()->create();

    $booking->syncDates([today()->toDateString(), today()->addWeek()->toDateString()]);

    expect($booking->hasFinished())->toBeFalse();
});

// A stay is over at checkout, which is a morning the room is already back on sale.
test('a stay is finished once the guest has checked out', function () {
    $room = Room::factory()->withRates([24 => 2500])->create();
    // Three days back for a two-night stay, so checkout is yesterday whatever the clock says.
    $booking = RoomBooking::factory()->for($room)->overnight(2)->create([
        'starts_at' => now()->subDays(3)->setTime(14, 0),
    ]);

    expect($booking->hasFinished())->toBeTrue()
        ->and($booking->ends_at->isPast())->toBeTrue();
});

test('a stay still running is not finished', function () {
    $room = Room::factory()->withRates([24 => 2500])->create();
    $booking = RoomBooking::factory()->for($room)->overnight(3)->create([
        'starts_at' => now()->subDay()->setTime(14, 0),
    ]);

    expect($booking->hasFinished())->toBeFalse();
});

test('a catering order reports the same way', function () {
    $order = CateringOrder::factory()->create();

    $order->syncDates([today()->subDay()->toDateString()]);

    expect($order->hasFinished())->toBeTrue()
        ->and($order->lastDate()->toDateString())->toBe(today()->subDay()->toDateString());
});
