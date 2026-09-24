<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Support\PayMongo;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * A complete, valid hall booking form.
 *
 * @return array<string, mixed>
 */
function checkoutInput(Hall $hall): array
{
    return [
        'hall_id' => $hall->id,
        'dates' => [now()->addWeek()->toDateString()],
        'start_hour' => 8,
        'end_hour' => 12,
        'include_skirting' => true,
        'guest_name' => 'Juan dela Cruz',
        'guest_phone' => '09171234567',
        'guest_email' => 'juan@example.com',
    ];
}

beforeEach(function () {
    // Stray requests are barred for every test here, but the stub itself is set per test:
    // Http::fake() accumulates stubs and the first match wins, so a success stub set here
    // would shadow the failure stubs the last few tests need.
    Http::preventStrayRequests();

    $this->hall = Hall::factory()->create([
        'name' => 'Grand Ballroom',
        'rent_price' => 8000,
        'skirting_price' => 5000,
    ]);

    $this->submit = function () {
        $component = Livewire::test('pages::booking.function-hall');

        foreach (checkoutInput($this->hall) as $field => $value) {
            $component->set($field, $value);
        }

        return $component->call('proceedToPayment');
    };
});

test('submitting a booking sends the guest to the checkout page', function () {
    fakePayMongo();

    ($this->submit)()->assertRedirect(FAKE_CHECKOUT_URL);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/checkout_sessions'));
});

/*
|--------------------------------------------------------------------------
| What is actually sent
|--------------------------------------------------------------------------
|
| A missing × 100 undercharges by 99% and an extra one overcharges a hundredfold, so what
| goes over the wire is asserted rather than trusted.
|
*/

test('the amount is sent in centavos', function () {
    fakePayMongo();

    ($this->submit)();

    // ₱6,500 downpayment on a ₱13,000 booking.
    Http::assertSent(fn (Request $request) => data_get($request->data(), 'data.attributes.line_items.0.amount') === 650_000);
});

test('the booking reference is sent so the payment can be matched to it', function () {
    fakePayMongo();

    ($this->submit)();

    $reference = Booking::sole()->reference;

    Http::assertSent(function (Request $request) use ($reference) {
        return data_get($request->data(), 'data.attributes.reference_number') === $reference
            && data_get($request->data(), 'data.attributes.metadata.reference') === $reference
            && data_get($request->data(), 'data.attributes.metadata.type') === 'hall';
    });
});

// Without a signature the cancel URL would let anyone release a stranger's dates.
test('the return and cancel urls are signed', function () {
    fakePayMongo();

    ($this->submit)();

    Http::assertSent(function (Request $request) {
        $attributes = data_get($request->data(), 'data.attributes');

        return str_contains($attributes['success_url'], 'signature=')
            && str_contains($attributes['cancel_url'], 'signature=');
    });
});

test('the guest details are sent so PayMongo can receipt them', function () {
    fakePayMongo();

    ($this->submit)();

    Http::assertSent(fn (Request $request) => data_get($request->data(), 'data.attributes.billing.email') === 'juan@example.com');
});

/**
 * QR Ph is the resort's only active method — it settles over InstaPay, so it needs no
 * merchant wallet, and the e-wallet methods sit inactive on the account. Naming a method
 * the account does not have narrows the checkout page to nothing, and the guest only finds
 * out after committing to a booking, so what is asked for is pinned here.
 */
test('QR Ph is what the guest is offered', function () {
    fakePayMongo();

    ($this->submit)();

    Http::assertSent(fn (Request $request) => data_get($request->data(), 'data.attributes.payment_method_types') === ['qrph']);
});

/**
 * PayMongo requires the list — "Parameter payment_method_types is required" — so there is
 * no offer-everything option, and a server whose .env was never updated must not fail
 * every booking. The config falls back rather than sending nothing.
 */
test('an unset method list falls back to the default rather than sending nothing', function () {
    config(['services.paymongo.methods' => PayMongo::DEFAULT_METHODS]);

    fakePayMongo();

    ($this->submit)();

    Http::assertSent(fn (Request $request) => data_get($request->data(), 'data.attributes.payment_method_types') === ['qrph']);
});

// Belt and braces: if the list is emptied anyway, say so rather than letting PayMongo
// answer with a pointer into a JSON document.
test('an empty method list is refused with a message naming the setting', function () {
    config(['services.paymongo.methods' => []]);

    ($this->submit)()
        ->assertHasErrors('dates')
        ->assertDontSee('payment_method_types');

    expect(Booking::count())->toBe(0);
    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| The booking behind the redirect
|--------------------------------------------------------------------------
*/

test('the booking is written before the redirect so it holds its dates', function () {
    fakePayMongo();

    ($this->submit)();

    $booking = Booking::sole();

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->payment_status)->toBe(PaymentStatus::Awaiting)
        ->and($booking->payment_session_id)->toBe('cs_test_fake')
        ->and($booking->payment_expires_at)->not->toBeNull()
        // Pending blocks, so nobody else can take these dates while the guest pays.
        ->and(Booking::query()->blocking()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| When PayMongo cannot be reached
|--------------------------------------------------------------------------
*/

// A booking nobody can pay for must not be left sitting on dates.
test('a booking is not left holding dates when the gateway refuses', function () {
    Http::fake(['*/checkout_sessions*' => Http::response(['errors' => [['detail' => 'Nope.']]], 400)]);

    ($this->submit)()->assertHasErrors('dates');

    expect(Booking::count())->toBe(0);
});

test('the guest is told plainly when the gateway is unreachable', function () {
    Http::fake(['*/checkout_sessions*' => Http::response([], 500)]);

    ($this->submit)()->assertSee('could not reach the payment provider', escape: false);
});

test('nothing is bookable at all when no payment key is configured', function () {
    config(['services.paymongo.secret_key' => null]);

    ($this->submit)()->assertHasErrors('dates');

    expect(Booking::count())->toBe(0);
    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Money conversion
|--------------------------------------------------------------------------
*/

test('pesos convert to centavos and back', function () {
    expect(PayMongo::centavos(6_500))->toBe(650_000)
        ->and(PayMongo::pesos(650_000))->toBe(6_500)
        // A stray centavo is a rounding artefact, not a part-payment.
        ->and(PayMongo::pesos(649_999))->toBe(6_500);
});

/**
 * A ₱20 hall is a ₱10 downpayment, under PayMongo's floor. This has to read as a plain
 * message about the amount — not as "we could not reach the payment provider", which is
 * untrue and sends the guest away to try again at something that will never work.
 */
test('an amount below the gateway minimum is refused before it is sent', function () {
    $hall = Hall::factory()->create(['rent_price' => 20, 'skirting_price' => 0]);

    Livewire::test('pages::booking.function-hall')
        ->set('hall_id', $hall->id)
        ->set('dates', [now()->addWeek()->toDateString()])
        ->set('start_hour', 8)
        ->set('end_hour', 12)
        ->set('include_skirting', false)
        ->set('guest_name', 'Juan dela Cruz')
        ->set('guest_phone', '09171234567')
        ->set('guest_email', 'juan@example.com')
        ->call('proceedToPayment')
        ->assertHasErrors('dates')
        ->assertSee('below the ₱20', escape: false)
        ->assertDontSee('could not reach the payment provider');

    // Nothing written, so no dates are left held by a booking that cannot be paid for.
    expect(Booking::count())->toBe(0);
    Http::assertNothingSent();
});

// The guest's own way out, rather than being told to telephone the resort.
test('a room whose downpayment is too small points at paying in full', function () {
    $room = Room::factory()->withRates([6 => 20])->create();

    $component = Livewire::test('pages::booking.rooms')
        ->set('room_id', $room->id)
        ->set('dates', [now()->addWeek()->toDateString()])
        ->set('stay_mode', 'day')
        ->set('entry_hour', 14)
        ->set('rate_id', $room->rates()->sole()->id)
        ->set('guest_name', 'Juan dela Cruz')
        ->set('guest_phone', '09171234567')
        ->set('guest_email', 'juan@example.com');

    $component->call('proceedToPayment')
        ->assertHasErrors('dates')
        ->assertSee('Pay in full', escape: false);

    expect(RoomBooking::count())->toBe(0);

    // And taking that advice gets them through: the whole ₱20 clears the floor.
    fakePayMongo();

    $component->set('payment_option', 'full')
        ->call('proceedToPayment')
        ->assertHasNoErrors()
        ->assertRedirect(FAKE_CHECKOUT_URL);

    expect(RoomBooking::sole()->amount_paid)->toBe(20);
});
