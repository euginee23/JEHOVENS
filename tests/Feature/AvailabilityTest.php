<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Support\Availability;
use Illuminate\Support\Carbon;

/**
 * The window the booking calendars ask about — a fortnight from the given day is plenty
 * to see a whole booking and the days around it.
 */
function window(string $from, int $days = 14): array
{
    return [Carbon::parse($from), Carbon::parse($from)->addDays($days)];
}

beforeEach(function () {
    $this->hall = Hall::factory()->create(['name' => 'Grand Ballroom']);
    $this->room = Room::factory()->withRates([6 => 1200, 24 => 2500])->create();
    $this->date = now()->addWeek()->toDateString();
});

test('a hall day with hours still free is only partly booked', function () {
    Booking::factory()->for($this->hall)->create([
        'start_date' => $this->date,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    $availability = Availability::forHall($this->hall->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeFalse()
        ->and($availability->isPartial($this->date))->toBeTrue()
        ->and($availability->busyHours($this->date))->toBe(['8AM–12PM']);
});

test('a hall day booked from opening to closing is unavailable', function () {
    // Shaped directly: the form only sells four-hour blocks, so no single booking a
    // guest could make covers the whole day.
    Booking::factory()->for($this->hall)->create([
        'start_date' => $this->date,
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    expect(Availability::forHall($this->hall->id, ...window($this->date))->isUnavailable($this->date))
        ->toBeTrue();
});

test('two bookings that together cover the day close it off', function () {
    Booking::factory()->for($this->hall)->create([
        'start_date' => $this->date,
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => 15,
    ]);

    Booking::factory()->for($this->hall)->create([
        'start_date' => $this->date,
        'start_hour' => 15,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    expect(Availability::forHall($this->hall->id, ...window($this->date))->isUnavailable($this->date))
        ->toBeTrue();
});

test('a multi-day hall booking marks every day it runs for', function () {
    Booking::factory()->for($this->hall)->spanningDays(3)->create([
        'start_date' => $this->date,
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    $availability = Availability::forHall($this->hall->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeTrue()
        ->and($availability->isUnavailable(Carbon::parse($this->date)->addDay()->toDateString()))->toBeTrue()
        ->and($availability->isUnavailable(Carbon::parse($this->date)->addDays(2)->toDateString()))->toBeTrue()
        // The day after the range ends is free again.
        ->and($availability->isUnavailable(Carbon::parse($this->date)->addDays(3)->toDateString()))->toBeFalse();
});

test('a cancelled booking frees its day again', function () {
    Booking::factory()->for($this->hall)->cancelled()->create([
        'start_date' => $this->date,
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    $availability = Availability::forHall($this->hall->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeFalse()
        ->and($availability->isPartial($this->date))->toBeFalse();
});

test('another hall booked on the same day does not close this one', function () {
    $other = Hall::factory()->create();

    Booking::factory()->for($other)->create([
        'start_date' => $this->date,
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    expect(Availability::forHall($this->hall->id, ...window($this->date))->isUnavailable($this->date))
        ->toBeFalse();
});

test('a multi-night stay closes off every whole day it covers', function () {
    $startsAt = Carbon::parse($this->date)->setTime(14, 0);

    RoomBooking::factory()->for($this->room)->overnight(3)->create(['starts_at' => $startsAt]);

    $availability = Availability::forRoom($this->room->id, ...window($this->date));

    $day = fn (int $offset) => Carbon::parse($this->date)->addDays($offset)->toDateString();

    // Arrival day still has the morning free, and the departure day frees up at 2PM.
    expect($availability->isPartial($day(0)))->toBeTrue()
        ->and($availability->isUnavailable($day(0)))->toBeFalse()
        ->and($availability->isUnavailable($day(1)))->toBeTrue()
        ->and($availability->isUnavailable($day(2)))->toBeTrue()
        ->and($availability->isPartial($day(3)))->toBeTrue()
        ->and($availability->isUnavailable($day(3)))->toBeFalse();
});

test('a day-use room booking leaves the rest of the day open', function () {
    $startsAt = Carbon::parse($this->date)->setTime(8, 0);

    RoomBooking::factory()->for($this->room)->create([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHours(6),
        'hours' => 6,
    ]);

    $availability = Availability::forRoom($this->room->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeFalse()
        ->and($availability->isPartial($this->date))->toBeTrue();
});

test('a selection containing a fully booked day is rejected', function () {
    $taken = Carbon::parse($this->date)->addDay()->toDateString();

    Booking::factory()->for($this->hall)->create([
        'start_date' => $taken,
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    $availability = Availability::forHall($this->hall->id, ...window($this->date));

    expect($availability->anyUnavailable([$this->date, $taken]))->toBeTrue()
        // A selection that steps over the taken day is fine.
        ->and($availability->anyUnavailable([$this->date, Carbon::parse($this->date)->addDays(2)->toDateString()]))
        ->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Days chosen one at a time
|--------------------------------------------------------------------------
|
| A booking's days need not be consecutive, so availability has to read the days a booking
| actually covers rather than the span between its first and last.
|
*/

test('a hall booking on separate days leaves the days between them open', function () {
    $booking = Booking::factory()->for($this->hall)->create([
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    $first = $this->date;
    $last = Carbon::parse($this->date)->addDays(4)->toDateString();

    $booking->syncDates([$first, $last]);

    $availability = Availability::forHall($this->hall->id, ...window($this->date));

    expect($availability->isUnavailable($first))->toBeTrue()
        ->and($availability->isUnavailable($last))->toBeTrue();

    // The three days in between were never sold and must stay on offer.
    foreach ([1, 2, 3] as $offset) {
        $between = Carbon::parse($this->date)->addDays($offset)->toDateString();

        expect($availability->isUnavailable($between))->toBeFalse()
            ->and($availability->isPartial($between))->toBeFalse();
    }
});

test('day use on separate days leaves the days between them open', function () {
    $booking = RoomBooking::factory()->for($this->room)->create([
        'starts_at' => Carbon::parse($this->date)->setTime(8, 0),
        'ends_at' => Carbon::parse($this->date)->setTime(14, 0),
        'hours' => 6,
    ]);

    $last = Carbon::parse($this->date)->addDays(4)->toDateString();

    $booking->syncDates([$this->date, $last]);

    $availability = Availability::forRoom($this->room->id, ...window($this->date));

    expect($availability->isPartial($this->date))->toBeTrue()
        ->and($availability->isPartial($last))->toBeTrue()
        ->and($availability->busyHours($last))->not->toBeEmpty();

    $between = Carbon::parse($this->date)->addDays(2)->toDateString();

    expect($availability->isPartial($between))->toBeFalse()
        ->and($availability->isUnavailable($between))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Finished bookings
|--------------------------------------------------------------------------
*/

// The resort's own example: a booking marked completed puts its day back on sale, so the
// same day can be sold again to someone else.
test('a completed booking no longer blocks its day', function () {
    Booking::factory()->for($this->hall)->create([
        'start_date' => $this->date,
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
        'status' => BookingStatus::Completed,
    ]);

    $availability = Availability::forHall($this->hall->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeFalse()
        ->and($availability->isPartial($this->date))->toBeFalse();
});

test('a completed stay no longer blocks its room', function () {
    RoomBooking::factory()->for($this->room)->overnight(2)->create([
        'starts_at' => Carbon::parse($this->date)->setTime(14, 0),
        'status' => BookingStatus::Completed,
    ]);

    $availability = Availability::forRoom($this->room->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeFalse()
        ->and($availability->isPartial($this->date))->toBeFalse();
});

test('catering never closes a date, since it has no capacity rule', function () {
    $package = CateringPackage::factory()->create();

    CateringOrder::factory()->for($package, 'package')->create(['start_date' => $this->date]);

    $availability = Availability::forCateringPackage($package->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeFalse()
        ->and($availability->isPartial($this->date))->toBeFalse();
});
