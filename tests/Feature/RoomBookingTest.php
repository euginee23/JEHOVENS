<?php

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomPhoto;
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
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
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
    fillRoomBooking($this->room, [
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
    ])
        ->call('proceedToPayment')
        ->assertHasErrors(['start_date' => 'after_or_equal']);
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

test('a same-day booking is day use, priced at the chosen block', function () {
    $date = now()->addWeek()->toDateString();

    fillRoomBooking($this->room, ['start_date' => $date, 'end_date' => $date])
        ->call('confirmPayment')
        ->assertHasNoErrors();

    $booking = RoomBooking::sole();

    expect($booking)
        ->nights->toBe(0)
        ->hours->toBe(6)
        ->total->toBe(1_200)
        ->and($booking->isOvernight())->toBeFalse()
        ->and($booking->ends_at->toDateTimeString())
        ->toBe(Carbon::parse($date)->setTime(20, 0)->toDateTimeString());
});

test('a stay across several days is priced at the nightly rate per night', function () {
    $start = now()->addWeek()->toDateString();
    $end = now()->addWeek()->addDays(3)->toDateString();

    fillRoomBooking($this->room, ['start_date' => $start, 'end_date' => $end])
        ->call('confirmPayment')
        ->assertHasNoErrors();

    $booking = RoomBooking::sole();

    expect($booking)
        ->nights->toBe(3)
        ->hours->toBe(72)
        ->total->toBe(7_500)      // 2,500 nightly × 3 nights
        ->amount_paid->toBe(3_750)
        ->and($booking->isOvernight())->toBeTrue();

    // Check-out is the entry time on the last day, not midnight.
    expect($booking->ends_at->toDateTimeString())
        ->toBe(Carbon::parse($end)->setTime(14, 0)->toDateTimeString());
});

test('an overnight stay does not need a day-use duration', function () {
    fillRoomBooking($this->room, [
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
        'rate_id' => null,
    ])
        ->call('proceedToPayment')
        ->assertHasNoErrors();
});

test('a room with no overnight rate cannot be booked across days', function () {
    $dayUseOnly = Room::factory()->withRates([6 => 1200])->create(['name' => 'Cabana']);

    fillRoomBooking($dayUseOnly, [
        'room_id' => $dayUseOnly->id,
        'rate_id' => $dayUseOnly->rates()->sole()->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
    ])
        ->call('proceedToPayment')
        ->assertHasErrors('end_date');

    expect(RoomBooking::count())->toBe(0);
});

test('an overnight stay blocks every night it covers', function () {
    $startsAt = Carbon::parse(now()->addWeek()->toDateString())->setTime(14, 0);

    RoomBooking::factory()->for($this->room)->overnight(3)->create(['starts_at' => $startsAt]);

    // A day-use booking in the middle of that stay has nowhere to go.
    fillRoomBooking($this->room, [
        'start_date' => now()->addWeek()->addDay()->toDateString(),
        'end_date' => now()->addWeek()->addDay()->toDateString(),
        'entry_hour' => 10,
    ])
        ->call('proceedToPayment')
        ->assertHasErrors('start_date');

    expect(RoomBooking::count())->toBe(1);
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
        ->assertHasErrors('start_date');

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

test('a room with photos shows them on the booking page', function () {
    $room = Room::factory()->withRates()->create(['name' => 'Photographed Room']);

    RoomPhoto::factory()->count(2)->for($room)->sequence(
        ['path' => 'rooms/first.jpg'],
        ['path' => 'rooms/second.jpg'],
    )->create();

    $html = $this->get(route('booking.rooms'))->assertOk()->getContent();

    expect($html)->toContain('rooms/first.jpg')
        ->and($html)->toContain('rooms/second.jpg');
});

test('a room card crossfades but never renders dots', function () {
    // The card is a <button>; dots inside it would be nested interactive elements and
    // would steal the click that selects the room.
    $room = Room::factory()->withRates()->create();
    RoomPhoto::factory()->count(3)->for($room)->create();

    $html = $this->get(route('booking.rooms'))->getContent();

    $card = str($html)->after('wire:key="room-'.$room->id.'"')->before('</button>')->toString();

    expect($card)->toContain('x-data')
        ->and($card)->toContain('setInterval')
        ->and($card)->not->toContain('Show photo')   // the dots' screen-reader label
        ->and($card)->not->toContain('<button');     // no nested button of any kind
});

test('a room without photos still renders its card', function () {
    Room::factory()->withRates()->create(['name' => 'Plain Room']);

    $this->get(route('booking.rooms'))
        ->assertOk()
        ->assertSee('Plain Room');
});

test('the room details panel is pinned', function () {
    $html = $this->get(route('booking.rooms'))->getContent();
    $panel = str($html)->afterLast('bg-white p-6 shadow-sm shadow-brand-950/5 ring-1 ring-sand-200')->toString();

    expect($panel)->toContain('lg:sticky')
        ->and($panel)->toContain('lg:overflow-y-auto')
        ->and($html)->toContain('lg:items-start');
});

test('the room panel prompts, then names the selection and its rate card', function () {
    $room = Room::factory()->withRates([6 => 1200, 24 => 2500])->create(['name' => 'Family Room 201']);

    Livewire::test('pages::booking.rooms')
        ->assertSee('Pick a room from the list to get started')
        ->call('selectRoom', $room->id)
        ->assertSee('Selected')
        ->assertSee('Family Room 201')
        ->assertSee('6 h · ₱1,200')
        ->assertSee('24 h · ₱2,500')
        ->assertDontSee('Pick a room from the list');
});

/*
|--------------------------------------------------------------------------
| Availability search
|--------------------------------------------------------------------------
|
| The homepage's availability bar is a plain GET form that hands `date`, `entry`, and
| `hours` to this page. Rates belong to a room, so the requested duration is only
| resolved into a rate once the guest picks one.
|
*/

test('the page pre-fills itself from the availability bar', function () {
    $date = today()->addDay()->toDateString();

    Livewire::withQueryParams(['date' => $date, 'entry' => 14, 'hours' => 24])
        ->test('pages::booking.rooms')
        ->assertSet('start_date', $date)
        ->assertSet('entry_hour', 14)
        ->assertSet('preferred_hours', 24);
});

test('picking a room applies the duration the guest searched for', function () {
    $room = Room::factory()->withRates([6 => 1200, 24 => 2500])->create();
    $overnight = $room->rates()->where('hours', 24)->sole();

    Livewire::withQueryParams(['hours' => 24])
        ->test('pages::booking.rooms')
        ->call('selectRoom', $room->id)
        ->assertSet('rate_id', $overnight->id);
});

test('a searched duration this room does not sell leaves the picker empty', function () {
    $room = Room::factory()->withRates([6 => 1200])->create();

    Livewire::withQueryParams(['hours' => 24])
        ->test('pages::booking.rooms')
        ->call('selectRoom', $room->id)
        ->assertSet('rate_id', null);
});

/**
 * The query string is guest-editable, so a stale bookmark must not seed the form with a
 * value `rules()` will only reject after they have filled in everything else.
 */
test('an unusable date or entry hour from the query string is discarded', function () {
    Livewire::withQueryParams(['date' => today()->subDay()->toDateString(), 'entry' => 3])
        ->test('pages::booking.rooms')
        ->assertSet('start_date', '')
        ->assertSet('entry_hour', null);

    Livewire::withQueryParams(['date' => 'not-a-date'])
        ->test('pages::booking.rooms')
        ->assertSet('start_date', '');
});

test('today is still an acceptable check-in date from the query string', function () {
    Livewire::withQueryParams(['date' => today()->toDateString()])
        ->test('pages::booking.rooms')
        ->assertSet('start_date', today()->toDateString());
});
