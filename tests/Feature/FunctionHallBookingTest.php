<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hall;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A complete, valid set of form input for the given hall.
 *
 * @return array<string, mixed>
 */
function bookingInput(Hall $hall, array $overrides = []): array
{
    return array_merge([
        'hall_id' => $hall->id,
        'dates' => [now()->addWeek()->toDateString()],
        'start_hour' => 8,
        'end_hour' => 12,
        'include_skirting' => true,
        'guest_name' => 'Juan dela Cruz',
        'guest_phone' => '09171234567',
        'guest_email' => 'juan@example.com',
    ], $overrides);
}

/**
 * A Livewire test instance with the booking form filled in.
 */
function fillBooking(Hall $hall, array $overrides = []): Testable
{
    $component = Livewire::test('pages::booking.function-hall');

    foreach (bookingInput($hall, $overrides) as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

beforeEach(function () {
    fakePayMongo();

    $this->hall = Hall::factory()->create([
        'name' => 'Grand Ballroom',
        'rent_price' => 8000,
        'skirting_price' => 5000,
    ]);
});

test('the booking page renders with the bookable halls', function () {
    Hall::factory()->create(['name' => 'Garden Pavilion']);
    Hall::factory()->inactive()->create(['name' => 'Closed For Repairs']);

    $this->get(route('booking.function-hall'))
        ->assertOk()
        ->assertSee('Book a function hall')
        ->assertSee('Grand Ballroom')
        ->assertSee('Garden Pavilion')
        ->assertDontSee('Closed For Repairs');
});

test('the page says so when nothing is bookable', function () {
    Hall::query()->delete();

    $this->get(route('booking.function-hall'))
        ->assertOk()
        ->assertSee('No function halls are available to book right now.');
});

test('the quote prices rent per four-hour block plus a one-time skirting fee', function () {
    $quote = $this->hall->quote(hours: 8, includeSkirting: true);

    expect($quote)->toMatchArray([
        'blocks' => 2,
        'rent_total' => 16_000,
        'skirting_total' => 5_000,
        'total' => 21_000,
        'downpayment' => 10_500,
        'balance' => 10_500,
    ]);
});

test('skirting is left out of the quote when it is not wanted', function () {
    expect($this->hall->quote(hours: 4, includeSkirting: false))
        ->toMatchArray(['total' => 8_000, 'downpayment' => 4_000, 'balance' => 4_000]);
});

test('a multi-day quote charges rent and skirting for every day', function () {
    $quote = $this->hall->quote(hours: 8, includeSkirting: true, days: 3);

    expect($quote)->toMatchArray([
        'blocks' => 2,
        'rent_total' => 48_000,   // 8,000 × 2 blocks × 3 days
        'skirting_total' => 15_000,  // 5,000 × 3 days
        'total' => 63_000,
        'downpayment' => 31_500,
    ]);
});

test('a booking can run across several days', function () {
    $days = collect(range(0, 2))->map(fn (int $i) => now()->addWeek()->addDays($i)->toDateString())->all();

    fillBooking($this->hall, ['dates' => $days])
        ->call('proceedToPayment')
        ->assertHasNoErrors();

    $booking = Booking::sole();

    expect($booking)
        ->days->toBe(3)
        ->hours->toBe(4)   // hours are per day, not the total across the range
        ->rent_total->toBe(24_000)
        ->skirting_total->toBe(15_000)
        ->total->toBe(39_000);

    expect($booking->dateList())->toBe($days)
        ->and($booking->start_date->toDateString())->toBe($days[0])
        ->and($booking->end_date->toDateString())->toBe($days[2]);
});

/**
 * The headline change: days are taken one at a time, so a guest running an event on three
 * separate Saturdays pays for three days rather than the whole three weeks between them.
 */
test('a booking can cover days that are not consecutive', function () {
    $days = [
        now()->addWeek()->toDateString(),
        now()->addWeeks(2)->toDateString(),
        now()->addWeeks(3)->toDateString(),
    ];

    fillBooking($this->hall, ['dates' => $days])
        ->call('proceedToPayment')
        ->assertHasNoErrors();

    $booking = Booking::sole();

    expect($booking->days)->toBe(3)
        ->and($booking->dateList())->toBe($days)
        ->and($booking->rent_total)->toBe(24_000)   // three days, not fifteen
        ->and($booking->dates()->count())->toBe(3)
        ->and($booking->start_date->toDateString())->toBe($days[0])
        ->and($booking->end_date->toDateString())->toBe($days[2]);
});

test('a booking with no dates at all is refused', function () {
    fillBooking($this->hall, ['dates' => []])
        ->call('proceedToPayment')
        ->assertHasErrors(['dates' => 'required']);

    expect(Booking::count())->toBe(0);
});

test('a booking cannot cover more days than the limit', function () {
    $tooMany = collect(range(0, 30))->map(fn (int $i) => now()->addDays($i + 1)->toDateString())->all();

    fillBooking($this->hall, ['dates' => $tooMany])
        ->call('proceedToPayment')
        ->assertHasErrors(['dates' => 'max']);

    expect(Booking::count())->toBe(0);
});

test('a multi-day booking clashes with anything overlapping any of its days', function () {
    $existing = now()->addWeek()->addDay()->toDateString();

    Booking::factory()->for($this->hall)->create([
        'start_date' => $existing,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    // The selection includes the taken day, on the same hours.
    fillBooking($this->hall, [
        'dates' => collect(range(0, 2))->map(fn (int $i) => now()->addWeek()->addDays($i)->toDateString())->all(),
        'start_hour' => 8,
        'end_hour' => 12,
    ])
        ->call('proceedToPayment')
        ->assertHasErrors('dates');

    expect(Booking::count())->toBe(1);
});

// The point of taking days one at a time: the guest simply leaves the busy day out.
test('a selection that steps over a taken day is allowed', function () {
    $taken = now()->addWeek()->addDay()->toDateString();

    Booking::factory()->for($this->hall)->create([
        'start_date' => $taken,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, [
        'dates' => [now()->addWeek()->toDateString(), now()->addWeek()->addDays(2)->toDateString()],
        'start_hour' => 8,
        'end_hour' => 12,
    ])
        ->call('proceedToPayment')
        ->assertHasNoErrors();

    expect(Booking::count())->toBe(2);
});

test('a multi-day booking on different hours does not clash', function () {
    Booking::factory()->for($this->hall)->create([
        'start_date' => now()->addWeek()->addDay()->toDateString(),
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, [
        'dates' => collect(range(0, 2))->map(fn (int $i) => now()->addWeek()->addDays($i)->toDateString())->all(),
        'start_hour' => 12,
        'end_hour' => 16,
    ])
        ->call('proceedToPayment')
        ->assertHasNoErrors();

    expect(Booking::count())->toBe(2);
});

// Without this a guest who mis-taps is stuck with a highlighted day they never wanted,
// which is the complaint that prompted the change.
test('tapping a date adds it and tapping it again removes it', function () {
    $date = now()->addWeek()->toDateString();

    Livewire::test('pages::booking.function-hall')
        ->call('toggleDate', $date)
        ->assertSet('dates', [$date])
        ->call('toggleDate', $date)
        ->assertSet('dates', []);
});

test('dates are kept in calendar order however they are tapped', function () {
    $earlier = now()->addWeek()->toDateString();
    $later = now()->addWeeks(2)->toDateString();

    Livewire::test('pages::booking.function-hall')
        ->call('toggleDate', $later)
        ->call('toggleDate', $earlier)
        ->assertSet('dates', [$earlier, $later]);
});

test('clearing the dates empties the calendar', function () {
    Livewire::test('pages::booking.function-hall')
        ->call('toggleDate', now()->addWeek()->toDateString())
        ->call('toggleDate', now()->addWeeks(2)->toDateString())
        ->assertCount('dates', 2)
        ->call('clearDates')
        ->assertSet('dates', []);
});

test('the calendar refuses a date in the past', function () {
    Livewire::test('pages::booking.function-hall')
        ->call('toggleDate', now()->subDay()->toDateString())
        ->assertSet('dates', []);
});

test('the calendar stops taking dates at the limit', function () {
    $component = Livewire::test('pages::booking.function-hall');

    foreach (range(0, 30) as $offset) {
        $component->call('toggleDate', now()->addDays($offset + 1)->toDateString());
    }

    $component->assertCount('dates', 30);
});

test('the live price summary follows the form', function () {
    fillBooking($this->hall)
        ->assertSee('₱8,000')   // rent for one block
        ->assertSee('₱5,000')   // skirting
        ->assertSee('₱13,000')  // total
        ->assertSee('₱6,500');  // downpayment
});

test('a guest can book a hall without an account', function () {
    fillBooking($this->hall)
        ->call('proceedToPayment')
        ->assertHasNoErrors()
        ->assertRedirect(FAKE_CHECKOUT_URL);

    $booking = Booking::sole();

    expect($booking)
        ->hall_id->toBe($this->hall->id)
        ->user_id->toBeNull()
        ->guest_name->toBe('Juan dela Cruz')
        ->hours->toBe(4)
        ->total->toBe(13_000)
        ->downpayment->toBe(6_500)
        ->balance->toBe(6_500)
        ->status->toBe(BookingStatus::Pending)
        ->and($booking->reference)->toStartWith('JGR-')
        // Written before the redirect, which is what holds the dates while they pay.
        ->and($booking->payment_status)->toBe(PaymentStatus::Awaiting)
        ->and($booking->payment_session_id)->toBe('cs_test_fake')
        ->and($booking->payment_expires_at)->not->toBeNull();
});

test('a signed-in guest has their booking linked to their account', function () {
    $user = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);

    $this->actingAs($user);

    Livewire::test('pages::booking.function-hall')
        ->assertSet('guest_name', 'Maria Santos')
        ->assertSet('guest_email', 'maria@example.com');

    fillBooking($this->hall)->call('proceedToPayment')->assertHasNoErrors();

    expect(Booking::sole()->user_id)->toBe($user->id);
});

test('a hall must be chosen', function () {
    fillBooking($this->hall, ['hall_id' => null])
        ->call('proceedToPayment')
        ->assertHasErrors(['hall_id' => 'required']);
});

test('the booking date cannot be in the past', function () {
    fillBooking($this->hall, [
        'dates' => [now()->subDay()->toDateString()],
    ])
        ->call('proceedToPayment')
        ->assertHasErrors('dates');
});

test('a booking shorter than the minimum is refused', function () {
    fillBooking($this->hall, ['start_hour' => 8, 'end_hour' => 10])
        ->call('proceedToPayment')
        ->assertHasErrors('end_hour');

    expect(Booking::count())->toBe(0);
});

// The old form only offered whole blocks, so a 7:00 AM to 12:00 PM event — the most
// commonly asked-for morning slot — could not be booked at all.
test('a booking of any whole number of hours over the minimum is allowed', function () {
    fillBooking($this->hall, ['start_hour' => 7, 'end_hour' => 12])
        ->call('proceedToPayment')
        ->assertHasNoErrors();

    expect(Booking::sole())
        ->hours->toBe(5)
        ->rent_total->toBe(16_000)   // five hours runs into a second block, billed whole
        ->total->toBe(21_000);
});

test('the end time must be after the start time', function () {
    fillBooking($this->hall, ['start_hour' => 12, 'end_hour' => 8])
        ->call('proceedToPayment')
        ->assertHasErrors(['end_hour' => 'gt']);
});

test('a booking cannot run past closing time', function () {
    fillBooking($this->hall, ['start_hour' => 20, 'end_hour' => 24])
        ->call('proceedToPayment')
        ->assertHasErrors('end_hour');
});

test('the phone number must be an 11-digit mobile number', function () {
    fillBooking($this->hall, ['guest_phone' => '12345'])
        ->call('proceedToPayment')
        ->assertHasErrors(['guest_phone' => 'regex']);
});

test('a hall cannot be double-booked for an overlapping slot', function () {
    $date = now()->addWeek()->toDateString();

    Booking::factory()->for($this->hall)->create([
        'start_date' => $date,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, ['dates' => [$date], 'start_hour' => 8, 'end_hour' => 12])
        ->call('proceedToPayment')
        ->assertHasErrors('dates');

    expect(Booking::count())->toBe(1);
});

test('a cancelled booking frees its slot again', function () {
    $date = now()->addWeek()->toDateString();

    Booking::factory()->for($this->hall)->cancelled()->create([
        'start_date' => $date,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, ['dates' => [$date], 'start_hour' => 8, 'end_hour' => 12])
        ->call('proceedToPayment')
        ->assertHasNoErrors();

    expect(Booking::where('status', BookingStatus::Pending)->count())->toBe(1);
});

test('a non-overlapping slot on the same day is allowed', function () {
    $date = now()->addWeek()->toDateString();

    Booking::factory()->for($this->hall)->create([
        'start_date' => $date,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, ['dates' => [$date], 'start_hour' => 12, 'end_hour' => 16])
        ->call('proceedToPayment')
        ->assertHasNoErrors();

    expect(Booking::count())->toBe(2);
});

/**
 * Flux's `placeholder` renders `<option disabled selected>`, and a disabled option is not
 * a valid selection. After any Livewire re-render the browser fell back to showing the
 * first real option, so the field read as filled in while the value was still empty — and
 * the guest was told to choose something they could see already chosen.
 */
test('the pickers offer a real empty option rather than a disabled placeholder', function () {
    $html = Livewire::test('pages::booking.function-hall')
        ->call('toggleDate', now()->addWeek()->toDateString())
        ->html();

    expect($html)->toContain('Please select a start time')
        ->and($html)->not->toContain('disabled selected');
});

test('an error clears once the guest fills the field in', function () {
    $component = fillBooking($this->hall, ['guest_phone' => '12345']);

    $component->call('proceedToPayment')->assertHasErrors('guest_phone');

    $component->set('guest_phone', '09171234567')->assertHasNoErrors('guest_phone');
});

test('changing the start time clears an end time that no longer fits', function () {
    Livewire::test('pages::booking.function-hall')
        ->set('start_hour', 8)
        ->set('end_hour', 12)
        ->set('start_hour', 9)
        ->assertSet('end_hour', null);
});

test('the details panel is pinned so it stays visible while the list scrolls', function () {
    $html = $this->get(route('booking.function-hall'))->getContent();

    // The picker comes first; the details panel is the second card.
    $panel = str($html)->afterLast('bg-white p-6 shadow-sm shadow-brand-950/5 ring-1 ring-sand-200')->toString();

    expect($panel)->toContain('lg:sticky')
        ->and($panel)->toContain('lg:top-28')
        // Without a height cap the Proceed button is unreachable on a short screen.
        ->and($panel)->toContain('lg:overflow-y-auto');

    // sticky only works while the grid does not stretch its children.
    expect($html)->toContain('lg:items-start');
});

test('the panel prompts for a selection before one is made', function () {
    Hall::factory()->create();

    Livewire::test('pages::booking.function-hall')
        ->assertSee('Pick a hall from the list to get started')
        ->assertDontSee('Selected');
});

test('the panel names the selected hall and its figures', function () {
    $hall = Hall::factory()->create([
        'name' => 'Grand Ballroom',
        'rent_price' => 8000,
        'skirting_price' => 5000,
        'capacity' => 500,
    ]);

    Livewire::test('pages::booking.function-hall')
        ->call('selectHall', $hall->id)
        ->assertSee('Selected')
        ->assertSee('Grand Ballroom')
        ->assertSee('₱8,000 / 4 hrs')
        ->assertSee('Skirting ₱5,000')
        ->assertSee('up to 500 guests')
        ->assertDontSee('Pick a hall from the list');
});

test('switching hall replaces the figures shown', function () {
    $ballroom = Hall::factory()->create(['name' => 'Grand Ballroom', 'rent_price' => 8000]);
    $pavilion = Hall::factory()->create(['name' => 'Garden Pavilion', 'rent_price' => 5000]);

    Livewire::test('pages::booking.function-hall')
        ->call('selectHall', $ballroom->id)
        ->assertSee('₱8,000 / 4 hrs')
        ->call('selectHall', $pavilion->id)
        ->assertSee('₱5,000 / 4 hrs')
        ->assertDontSee('₱8,000 / 4 hrs');
});
