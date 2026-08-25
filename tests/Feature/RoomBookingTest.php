<?php

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A Livewire test instance with the room booking form filled in.
 */
function fillRoomBooking(Room $room, array $overrides = []): Testable
{
    $component = Livewire::test('pages::booking.rooms');

    $input = array_merge([
        'room_id' => $room->id,
        'checkin_date' => now()->addWeek()->toDateString(),
        'entry_hour' => 14,
        'rate_id' => $room->rates->firstWhere('hours', 6)->id,
        'payment_option' => 'downpayment',
        'guest_name' => 'Juan dela Cruz',
        'guest_phone' => '09171234567',
        'guest_email' => 'juan@example.com',
    ], $overrides);

    foreach ($input as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

beforeEach(function () {
    $this->room = Room::factory()
        ->withRates([6 => 1200, 12 => 1800, 24 => 2500])
        ->create(['name' => 'Standard Room 101']);

    $this->room->load('rates');
});

test('the booking page renders with the bookable rooms and their rates', function () {
    Room::factory()->inactive()->withRates()->create(['name' => 'Under Renovation']);

    $this->get(route('booking.rooms'))
        ->assertOk()
        ->assertSee('Book a room')
        ->assertSee('Standard Room 101')
        ->assertSee('6 h: ₱1,200')
        ->assertSee('24 h: ₱2,500')
        ->assertDontSee('Under Renovation');
});

test('the page says so when nothing is bookable', function () {
    Room::query()->delete();

    $this->get(route('booking.rooms'))
        ->assertOk()
        ->assertSee('No rooms are available to book right now.');
});

test('a downpayment is half the room rate', function () {
    $rate = $this->room->rates->firstWhere('hours', 12);

    expect($this->room->quote($rate, payInFull: false))
        ->toMatchArray(['total' => 1_800, 'amount_paid' => 900, 'balance' => 900]);
});

test('paying in full leaves no balance', function () {
    $rate = $this->room->rates->firstWhere('hours', 12);

    expect($this->room->quote($rate, payInFull: true))
        ->toMatchArray(['total' => 1_800, 'amount_paid' => 1_800, 'balance' => 0]);
});

test('the live price summary follows the payment option', function () {
    fillRoomBooking($this->room)
        ->assertSee('Paying now (50%)')
        ->assertSee('₱600')
        ->set('payment_option', 'full')
        ->assertSee('Paying now (100%)')
        ->assertSee('₱1,200');
});

test('a guest can book a room without an account', function () {
    fillRoomBooking($this->room)
        ->call('proceedToPayment')
        ->assertHasNoErrors()
        ->assertSet('showPayment', true)
        ->call('confirmPayment')
        ->assertHasNoErrors()
        ->assertSee('Booking received');

    $booking = RoomBooking::sole();

    expect($booking)
        ->room_id->toBe($this->room->id)
        ->user_id->toBeNull()
        ->hours->toBe(6)
        ->total->toBe(1_200)
        ->amount_paid->toBe(600)
        ->balance->toBe(600)
        ->pay_in_full->toBeFalse()
        ->status->toBe(BookingStatus::Pending)
        ->and($booking->reference)->toStartWith('JGR-R')
        ->and($booking->starts_at->format('Y-m-d H:i'))->toBe(now()->addWeek()->format('Y-m-d').' 14:00')
        ->and($booking->ends_at->format('H:i'))->toBe('20:00');
});

test('a stay that runs past midnight ends on the next day', function () {
    fillRoomBooking($this->room, [
        'entry_hour' => 22,
        'rate_id' => $this->room->rates->firstWhere('hours', 6)->id,
    ])->call('confirmPayment')->assertHasNoErrors();

    $booking = RoomBooking::sole();

    expect($booking->ends_at->toDateString())->toBe($booking->starts_at->copy()->addDay()->toDateString())
        ->and($booking->ends_at->format('H:i'))->toBe('04:00');
});

test('the guest is asked to arrive half an hour early', function () {
    fillRoomBooking($this->room, ['entry_hour' => 14])->assertSee('1:30 PM');

    fillRoomBooking($this->room)->call('confirmPayment');

    expect(RoomBooking::sole()->arriveBy()->format('H:i'))->toBe('13:30');
});

test('a signed-in guest has their booking linked to their account', function () {
    $user = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);

    $this->actingAs($user);

    Livewire::test('pages::booking.rooms')
        ->assertSet('guest_name', 'Maria Santos')
        ->assertSet('guest_email', 'maria@example.com');

    fillRoomBooking($this->room)->call('confirmPayment')->assertHasNoErrors();

    expect(RoomBooking::sole()->user_id)->toBe($user->id);
});

test('a room must be chosen', function () {
    fillRoomBooking($this->room, ['room_id' => null])
        ->call('proceedToPayment')
        ->assertHasErrors(['room_id' => 'required']);
});

test('the check-in date cannot be in the past', function () {
    fillRoomBooking($this->room, ['checkin_date' => now()->subDay()->toDateString()])
        ->call('proceedToPayment')
        ->assertHasErrors(['checkin_date' => 'after_or_equal']);
});

test('an entry time is required', function () {
    fillRoomBooking($this->room, ['entry_hour' => null])
        ->call('proceedToPayment')
        ->assertHasErrors(['entry_hour' => 'required']);
});

test('a duration is required', function () {
    fillRoomBooking($this->room, ['rate_id' => null])
        ->call('proceedToPayment')
        ->assertHasErrors(['rate_id' => 'required']);
});

test('a rate belonging to another room is rejected', function () {
    $otherRoom = Room::factory()->withRates([6 => 9999])->create();

    fillRoomBooking($this->room, ['rate_id' => $otherRoom->rates()->sole()->id])
        ->call('proceedToPayment')
        ->assertHasErrors('rate_id');

    expect(RoomBooking::count())->toBe(0);
});

test('switching rooms clears the chosen duration', function () {
    $otherRoom = Room::factory()->withRates()->create();

    Livewire::test('pages::booking.rooms')
        ->set('room_id', $this->room->id)
        ->set('rate_id', $this->room->rates->first()->id)
        ->call('selectRoom', $otherRoom->id)
        ->assertSet('rate_id', null);
});

test('the phone number must be an 11-digit mobile number', function () {
    fillRoomBooking($this->room, ['guest_phone' => '12345'])
        ->call('proceedToPayment')
        ->assertHasErrors(['guest_phone' => 'regex']);
});

test('a room cannot be double-booked for an overlapping stay', function () {
    $startsAt = Carbon::parse(now()->addWeek()->toDateString())->setTime(14, 0);

    RoomBooking::factory()->for($this->room)->create([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHours(6),
        'hours' => 6,
    ]);

    fillRoomBooking($this->room, ['entry_hour' => 16])
        ->call('proceedToPayment')
        ->assertHasErrors('checkin_date');

    expect(RoomBooking::count())->toBe(1);
});

test('a stay starting when another ends is allowed', function () {
    $startsAt = Carbon::parse(now()->addWeek()->toDateString())->setTime(8, 0);

    RoomBooking::factory()->for($this->room)->create([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHours(6),
        'hours' => 6,
    ]);

    fillRoomBooking($this->room, ['entry_hour' => 14])
        ->call('confirmPayment')
        ->assertHasNoErrors();

    expect(RoomBooking::count())->toBe(2);
});

test('a cancelled booking frees the room again', function () {
    $startsAt = Carbon::parse(now()->addWeek()->toDateString())->setTime(14, 0);

    RoomBooking::factory()->for($this->room)->cancelled()->create([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHours(6),
        'hours' => 6,
    ]);

    fillRoomBooking($this->room)
        ->call('confirmPayment')
        ->assertHasNoErrors();

    expect(RoomBooking::where('status', BookingStatus::Pending)->count())->toBe(1);
});

test('another room is still free at the same time', function () {
    $otherRoom = Room::factory()->withRates([6 => 1200])->create();
    $startsAt = Carbon::parse(now()->addWeek()->toDateString())->setTime(14, 0);

    RoomBooking::factory()->for($otherRoom)->create([
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHours(6),
        'hours' => 6,
    ]);

    fillRoomBooking($this->room)
        ->call('confirmPayment')
        ->assertHasNoErrors();

    expect(RoomBooking::count())->toBe(2);
});
