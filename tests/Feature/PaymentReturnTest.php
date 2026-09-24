<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hall;
use App\Notifications\ReservationReceived;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The signed URL PayMongo sends a guest back to.
 */
function returnUrl(Booking $booking, string $route = 'payment.return'): string
{
    return URL::signedRoute($route, ['type' => 'hall', 'reference' => $booking->reference]);
}

/**
 * Stub the session lookup the return page makes.
 */
function fakeSessionLookup(bool $paid, int $paidAmount = 0): void
{
    Http::preventStrayRequests();

    Http::fake([
        '*/checkout_sessions/*' => Http::response(['data' => fakeCheckoutSession($paid, $paidAmount)]),
    ]);
}

beforeEach(function () {
    Notification::fake();

    $this->hall = Hall::factory()->create(['rent_price' => 8000, 'skirting_price' => 5000]);

    $this->booking = Booking::factory()->for($this->hall)->create([
        'guest_email' => 'juan@example.com',
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Awaiting,
        'payment_session_id' => 'cs_test_fake',
        'payment_expires_at' => now()->addHour(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Coming back having paid
|--------------------------------------------------------------------------
|
| The redirect itself proves only that the guest got back here. What actually happened is
| asked of PayMongo, so a guest cannot confirm their own booking by opening the URL.
|
*/

test('the return page asks PayMongo what happened rather than trusting the redirect', function () {
    fakeSessionLookup(paid: true, paidAmount: $this->booking->downpayment);

    $this->get(returnUrl($this->booking))->assertRedirect(route('booking.function-hall'));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout_sessions/cs_test_fake'));

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('a guest coming back paid sees their confirmation', function () {
    fakeSessionLookup(paid: true, paidAmount: $this->booking->downpayment);

    $this->get(returnUrl($this->booking));

    $this->followingRedirects()
        ->get(returnUrl($this->booking))
        ->assertOk()
        ->assertSee('Booking received')
        ->assertSee($this->booking->reference)
        ->assertSee('pay_test_fake');
});

test('a guest coming back unpaid is not confirmed', function () {
    fakeSessionLookup(paid: false);

    $this->get(returnUrl($this->booking))->assertRedirect(route('booking.function-hall'));

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending)
        ->and($this->booking->fresh()->payment_status)->toBe(PaymentStatus::Awaiting);
});

// PayMongo being briefly unreachable must not look like a failed booking: the webhook is
// still on its way.
test('a gateway that cannot be reached still returns the guest to the page', function () {
    Http::preventStrayRequests();
    Http::fake(['*/checkout_sessions/*' => Http::response([], 500)]);

    $this->get(returnUrl($this->booking))->assertRedirect(route('booking.function-hall'));

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending);
});

/*
|--------------------------------------------------------------------------
| The webhook and the guest racing each other
|--------------------------------------------------------------------------
*/

test('a booking the webhook already confirmed is not confirmed twice', function () {
    $this->booking->recordPayment(PaymentStatus::Paid, ['payment_reference' => 'pay_earlier']);
    $this->booking->transitionTo(BookingStatus::Confirmed, notify: false);

    Http::preventStrayRequests();

    $this->get(returnUrl($this->booking))->assertRedirect(route('booking.function-hall'));

    // Nothing was asked of PayMongo and nothing was written again.
    Http::assertNothingSent();

    expect($this->booking->fresh()->payment_reference)->toBe('pay_earlier');
    Notification::assertNothingSent();
});

test('confirming twice sends only one receipt', function () {
    fakeSessionLookup(paid: true, paidAmount: $this->booking->downpayment);

    $this->get(returnUrl($this->booking));
    $this->get(returnUrl($this->booking));

    Notification::assertSentOnDemandTimes(ReservationReceived::class, 1);
});

/*
|--------------------------------------------------------------------------
| Backing out
|--------------------------------------------------------------------------
*/

test('cancelling releases the dates at once', function () {
    Http::preventStrayRequests();

    $this->get(returnUrl($this->booking, 'payment.cancel'))->assertRedirect(route('booking.function-hall'));

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->payment_status)->toBe(PaymentStatus::Expired)
        // Cancelled does not block, so the dates are on sale again.
        ->and(Booking::query()->blocking()->count())->toBe(0);
});

test('cancelling a booking that was already paid leaves it alone', function () {
    $this->booking->recordPayment(PaymentStatus::Paid);
    $this->booking->transitionTo(BookingStatus::Confirmed, notify: false);

    Http::preventStrayRequests();

    $this->get(returnUrl($this->booking, 'payment.cancel'));

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

/*
|--------------------------------------------------------------------------
| The signature on the URL
|--------------------------------------------------------------------------
|
| A booking reference is six characters. Unsigned, the cancel URL would let a stranger
| release somebody else's dates by guessing.
|
*/

test('an unsigned return or cancel url is refused', function (string $route) {
    Http::preventStrayRequests();

    $this->get(route($route, ['type' => 'hall', 'reference' => $this->booking->reference]))
        ->assertForbidden();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending);
})->with(['payment.return', 'payment.cancel']);

test('a tampered signature is refused', function () {
    Http::preventStrayRequests();

    $this->get(returnUrl($this->booking, 'payment.cancel').'x')->assertForbidden();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending);
});

test('a reference that matches nothing is handled without error', function () {
    Http::preventStrayRequests();

    $this->get(URL::signedRoute('payment.return', ['type' => 'hall', 'reference' => 'JGR-NOPE']))
        ->assertRedirect(route('booking.function-hall'));
});
