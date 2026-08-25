<?php

use App\Enums\BookingStatus;
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
        'booking_date' => now()->addWeek()->toDateString(),
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
        ->assertSet('showPayment', true)
        ->call('confirmPayment')
        ->assertHasNoErrors()
        ->assertSet('showPayment', false)
        ->assertSee('Booking received');

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
        ->and($booking->reference)->toStartWith('JGR-');
});

test('a signed-in guest has their booking linked to their account', function () {
    $user = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);

    $this->actingAs($user);

    Livewire::test('pages::booking.function-hall')
        ->assertSet('guest_name', 'Maria Santos')
        ->assertSet('guest_email', 'maria@example.com');

    fillBooking($this->hall)->call('confirmPayment')->assertHasNoErrors();

    expect(Booking::sole()->user_id)->toBe($user->id);
});

test('a hall must be chosen', function () {
    fillBooking($this->hall, ['hall_id' => null])
        ->call('proceedToPayment')
        ->assertHasErrors(['hall_id' => 'required']);
});

test('the booking date cannot be in the past', function () {
    fillBooking($this->hall, ['booking_date' => now()->subDay()->toDateString()])
        ->call('proceedToPayment')
        ->assertHasErrors(['booking_date' => 'after_or_equal']);
});

test('the stay must be a whole number of four-hour blocks', function () {
    fillBooking($this->hall, ['start_hour' => 8, 'end_hour' => 14])
        ->call('proceedToPayment')
        ->assertHasErrors('end_hour');

    expect(Booking::count())->toBe(0);
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
        'booking_date' => $date,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, ['booking_date' => $date, 'start_hour' => 8, 'end_hour' => 12])
        ->call('proceedToPayment')
        ->assertHasErrors('booking_date');

    expect(Booking::count())->toBe(1);
});

test('a cancelled booking frees its slot again', function () {
    $date = now()->addWeek()->toDateString();

    Booking::factory()->for($this->hall)->cancelled()->create([
        'booking_date' => $date,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, ['booking_date' => $date, 'start_hour' => 8, 'end_hour' => 12])
        ->call('confirmPayment')
        ->assertHasNoErrors();

    expect(Booking::where('status', BookingStatus::Pending)->count())->toBe(1);
});

test('a non-overlapping slot on the same day is allowed', function () {
    $date = now()->addWeek()->toDateString();

    Booking::factory()->for($this->hall)->create([
        'booking_date' => $date,
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    fillBooking($this->hall, ['booking_date' => $date, 'start_hour' => 12, 'end_hour' => 16])
        ->call('confirmPayment')
        ->assertHasNoErrors();

    expect(Booking::count())->toBe(2);
});

test('changing the start time clears an end time that no longer fits', function () {
    Livewire::test('pages::booking.function-hall')
        ->set('start_hour', 8)
        ->set('end_hour', 12)
        ->set('start_hour', 9)
        ->assertSet('end_hour', null);
});
