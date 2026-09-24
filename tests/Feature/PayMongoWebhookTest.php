<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Hall;
use App\Notifications\NewReservationAlert;
use App\Notifications\ReservationPaymentFailed;
use App\Notifications\ReservationReceived;
use App\Support\PayMongoSignature;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * One webhook delivery, signed the way PayMongo signs them.
 *
 * @param  array<string, mixed>  $session
 * @return array{payload: string, headers: array<string, string>}
 */
function signedWebhook(string $type, array $session, string $eventId = 'evt_test_1'): array
{
    $payload = json_encode([
        'data' => [
            'id' => $eventId,
            'attributes' => ['type' => $type, 'data' => $session],
        ],
    ]);

    $timestamp = now()->getTimestamp();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, config('services.paymongo.webhook_secret'));

    return [
        'payload' => $payload,
        'headers' => ['Paymongo-Signature' => "t={$timestamp},te={$signature},li={$signature}"],
    ];
}

/**
 * Post a webhook to the endpoint as PayMongo would.
 */
function deliverWebhook(array $webhook): TestResponse
{
    return test()->call(
        'POST',
        route('webhooks.paymongo'),
        server: ['HTTP_PAYMONGO_SIGNATURE' => $webhook['headers']['Paymongo-Signature'], 'CONTENT_TYPE' => 'application/json'],
        content: $webhook['payload'],
    );
}

/**
 * A checkout session for the given booking, paid in full for its downpayment.
 *
 * @return array<string, mixed>
 */
function sessionFor(Booking $booking, ?int $paidAmount = null): array
{
    $session = fakeCheckoutSession(paid: true, paidAmount: $paidAmount ?? $booking->downpayment);

    $session['attributes']['reference_number'] = $booking->reference;
    $session['attributes']['metadata'] = ['type' => 'hall', 'reference' => $booking->reference];

    return $session;
}

beforeEach(function () {
    Notification::fake();

    $this->hall = Hall::factory()->create(['rent_price' => 8000, 'skirting_price' => 5000]);

    $this->booking = Booking::factory()->for($this->hall)->create([
        'guest_email' => 'juan@example.com',
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Awaiting,
        'payment_expires_at' => now()->addHour(),
    ]);
});

/*
|--------------------------------------------------------------------------
| A payment arriving
|--------------------------------------------------------------------------
*/

test('a paid checkout confirms the booking and records the payment reference', function () {
    deliverWebhook(signedWebhook('checkout_session.payment.paid', sessionFor($this->booking)))
        ->assertNoContent();

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        // This is the reference the resort verifies a booking against.
        ->and($booking->payment_reference)->toBe('pay_test_fake')
        ->and($booking->payment_method)->toBe('gcash')
        ->and($booking->paid_amount)->toBe($booking->downpayment)
        ->and($booking->paid_at)->not->toBeNull()
        // The hold is over; the booking stands on its own now.
        ->and($booking->payment_expires_at)->toBeNull();
});

test('a paid checkout emails the guest their receipt and alerts the resort', function () {
    deliverWebhook(signedWebhook('checkout_session.payment.paid', sessionFor($this->booking)));

    Notification::assertSentOnDemand(ReservationReceived::class);
    Notification::assertSentOnDemand(NewReservationAlert::class);
});

/*
|--------------------------------------------------------------------------
| Deliveries that must not be acted on twice
|--------------------------------------------------------------------------
|
| PayMongo retries until it gets a 2xx, so the same event can arrive more than once. A
| second run would confirm the booking again and send a second receipt.
|
*/

test('the same event delivered twice is only acted on once', function () {
    $webhook = signedWebhook('checkout_session.payment.paid', sessionFor($this->booking));

    deliverWebhook($webhook)->assertNoContent();
    deliverWebhook($webhook)->assertNoContent();

    expect(DB::table('payment_webhook_events')->count())->toBe(1);

    Notification::assertSentOnDemandTimes(ReservationReceived::class, 1);
});

test('a delivery is recorded as handled', function () {
    deliverWebhook(signedWebhook('checkout_session.payment.paid', sessionFor($this->booking)));

    $event = DB::table('payment_webhook_events')->sole();

    expect($event->event_id)->toBe('evt_test_1')
        ->and($event->event_type)->toBe('checkout_session.payment.paid')
        ->and($event->processed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Deliveries that must be refused
|--------------------------------------------------------------------------
|
| This is a public URL. Without the signature check anyone who found it could confirm
| bookings for nothing.
|
*/

test('a delivery with no signature is refused', function () {
    $this->call('POST', route('webhooks.paymongo'), content: '{}', server: ['CONTENT_TYPE' => 'application/json'])
        ->assertStatus(400);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending);
});

test('a delivery signed with the wrong secret is refused', function () {
    $webhook = signedWebhook('checkout_session.payment.paid', sessionFor($this->booking));
    $webhook['headers']['Paymongo-Signature'] = 't='.now()->getTimestamp().',te=deadbeef,li=deadbeef';

    deliverWebhook($webhook)->assertStatus(400);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending)
        ->and(DB::table('payment_webhook_events')->count())->toBe(0);
});

// A signature captured off the wire is worthless once it is stale.
test('a correctly signed but stale delivery is refused', function () {
    $payload = json_encode(['data' => ['id' => 'evt_old', 'attributes' => ['type' => 'checkout_session.payment.paid', 'data' => sessionFor($this->booking)]]]);

    $timestamp = now()->subHour()->getTimestamp();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, config('services.paymongo.webhook_secret'));

    $this->call('POST', route('webhooks.paymongo'), content: $payload, server: [
        'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ])->assertStatus(400);

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending);
});

/*
|--------------------------------------------------------------------------
| Payments that went wrong
|--------------------------------------------------------------------------
*/

test('a failed payment leaves the booking pending so the guest can retry', function () {
    deliverWebhook(signedWebhook('payment.failed', sessionFor($this->booking)))->assertNoContent();

    $booking = $this->booking->fresh();

    expect($booking->payment_status)->toBe(PaymentStatus::Failed)
        // Still Pending, and still holding its dates until the hold runs out.
        ->and($booking->status)->toBe(BookingStatus::Pending);

    Notification::assertSentOnDemand(ReservationPaymentFailed::class);
});

// Paying less than was asked is not a confirmation; staff have to look at it.
test('a short payment does not confirm the booking', function () {
    $session = sessionFor($this->booking, paidAmount: 1);

    deliverWebhook(signedWebhook('checkout_session.payment.paid', $session))->assertNoContent();

    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->payment_status)->toBe(PaymentStatus::Awaiting);

    Notification::assertNotSentTo(new AnonymousNotifiable, ReservationReceived::class);
});

test('an event about nothing this application knows is accepted and ignored', function () {
    $session = sessionFor($this->booking);

    deliverWebhook(signedWebhook('payment.refunded', $session))->assertNoContent();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending);
});

test('an event with no booking attached is accepted and ignored', function () {
    $session = fakeCheckoutSession(paid: true, paidAmount: 100);

    deliverWebhook(signedWebhook('checkout_session.payment.paid', $session))->assertNoContent();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::Pending);
});

/*
|--------------------------------------------------------------------------
| Signature verification on its own
|--------------------------------------------------------------------------
|
| Checked against a hand-computed digest so the suite is not merely agreeing with itself.
|
*/

test('the signature check accepts a digest computed by hand', function () {
    $payload = '{"hello":"world"}';
    $timestamp = now()->getTimestamp();
    $expected = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsk_known');

    expect(PayMongoSignature::verify("t={$timestamp},te={$expected}", $payload, 'whsk_known'))->toBeTrue()
        ->and(PayMongoSignature::verify("t={$timestamp},li={$expected}", $payload, 'whsk_known'))->toBeTrue()
        ->and(PayMongoSignature::verify("t={$timestamp},te={$expected}", $payload, 'whsk_other'))->toBeFalse()
        ->and(PayMongoSignature::verify("t={$timestamp},te={$expected}", 'tampered', 'whsk_known'))->toBeFalse()
        ->and(PayMongoSignature::verify(null, $payload, 'whsk_known'))->toBeFalse()
        ->and(PayMongoSignature::verify("t={$timestamp},te={$expected}", $payload, null))->toBeFalse()
        ->and(PayMongoSignature::verify('nonsense', $payload, 'whsk_known'))->toBeFalse();
});
