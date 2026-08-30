<?php

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

test('a range crossing a fully booked day is not clear', function () {
    Booking::factory()->for($this->hall)->create([
        'start_date' => Carbon::parse($this->date)->addDay()->toDateString(),
        'start_hour' => Hall::OPENS_AT,
        'end_hour' => Hall::CLOSES_AT,
    ]);

    $availability = Availability::forHall($this->hall->id, ...window($this->date));

    expect($availability->rangeIsClear($this->date, Carbon::parse($this->date)->addDays(2)->toDateString()))
        ->toBeFalse()
        // A range stopping short of the taken day is still fine.
        ->and($availability->rangeIsClear($this->date, $this->date))
        ->toBeTrue();
});

test('catering never closes a date, since it has no capacity rule', function () {
    $package = CateringPackage::factory()->create();

    CateringOrder::factory()->for($package, 'package')->create(['start_date' => $this->date]);

    $availability = Availability::forCateringPackage($package->id, ...window($this->date));

    expect($availability->isUnavailable($this->date))->toBeFalse()
        ->and($availability->isPartial($this->date))->toBeFalse();
});
