<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\Hall;
use App\Models\ResortSetting;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Notifications\NewReservationAlert;
use App\Notifications\ReservationBalanceSettled;
use App\Notifications\ReservationHoldExpired;
use App\Notifications\ReservationPaymentFailed;
use App\Notifications\ReservationReceived;
use App\Notifications\ReservationStatusChanged;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Guests mostly book without an account, so every one of these is addressed to an email
 * rather than sent to a User.
 */
function addressedTo(string $email): Closure
{
    return fn ($notification, $channels, $notifiable) => $notifiable instanceof AnonymousNotifiable
        && $notifiable->routes['mail'] === $email;
}

beforeEach(function () {
    Notification::fake();

    $this->hall = Hall::factory()->create(['rent_price' => 8000, 'skirting_price' => 5000]);
});

test('placing a booking emails the guest a receipt', function () {
    $booking = Booking::factory()->for($this->hall)->create(['guest_email' => 'juan@example.com']);

    $booking->sendPlacementNotifications();

    Notification::assertSentOnDemand(ReservationReceived::class, addressedTo('juan@example.com'));
});

test('placing a booking alerts the resort', function () {
    config(['resort.notifications.admin_email' => 'frontdesk@jehovens.test']);

    Booking::factory()->for($this->hall)->create()->sendPlacementNotifications();

    Notification::assertSentOnDemand(NewReservationAlert::class, addressedTo('frontdesk@jehovens.test'));
});

test('confirming a booking emails the guest', function () {
    $booking = Booking::factory()->for($this->hall)->create(['guest_email' => 'juan@example.com']);

    expect($booking->transitionTo(BookingStatus::Confirmed))->toBeTrue();

    Notification::assertSentOnDemand(
        ReservationStatusChanged::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'juan@example.com'
            && $notification->reservation->status === BookingStatus::Confirmed,
    );
});

test('cancelling a booking emails the guest', function () {
    $booking = Booking::factory()->for($this->hall)->create();

    $booking->transitionTo(BookingStatus::Cancelled);

    Notification::assertSentOnDemand(
        ReservationStatusChanged::class,
        fn ($notification) => $notification->reservation->status === BookingStatus::Cancelled,
    );
});

test('a move that is not allowed sends nothing', function () {
    // Pending goes to Confirmed or Cancelled, never straight to Completed.
    $booking = Booking::factory()->for($this->hall)->create();

    expect($booking->transitionTo(BookingStatus::Completed))->toBeFalse();

    Notification::assertNothingSent();
});

test('settling the balance emails the guest a receipt', function () {
    $booking = Booking::factory()->for($this->hall)->confirmed()->create(['guest_email' => 'juan@example.com']);

    expect($booking->settleBalance())->toBeTrue();

    Notification::assertSentOnDemand(ReservationBalanceSettled::class, addressedTo('juan@example.com'));
});

/**
 * Completing a booking settles whatever was still owed, and the completed email says so
 * itself. A separate "balance received" email on top of it would be the guest's second
 * email about the same click.
 */
test('completing a booking that still owes money sends one email that covers the balance', function () {
    // Finished, because a booking can only be completed once its event is over.
    $booking = Booking::factory()->for($this->hall)->confirmed()->create([
        'guest_email' => 'juan@example.com',
        'start_date' => today()->subWeek()->toDateString(),
    ]);

    expect($booking->hasOutstandingBalance())->toBeTrue();
    expect($booking->transitionTo(BookingStatus::Completed))->toBeTrue();

    Notification::assertSentOnDemand(ReservationStatusChanged::class, addressedTo('juan@example.com'));
    Notification::assertSentOnDemandTimes(ReservationBalanceSettled::class, 0);
    expect($booking->fresh()->balance_settled_at)->not->toBeNull();
});

test('completing a booking that is already paid up sends only the status change', function () {
    $booking = Booking::factory()->for($this->hall)->confirmed()->create([
        'guest_email' => 'juan@example.com',
        'start_date' => today()->subWeek()->toDateString(),
    ]);
    $booking->settleBalance();

    Notification::fake();

    expect($booking->transitionTo(BookingStatus::Completed))->toBeTrue();

    Notification::assertSentOnDemand(ReservationStatusChanged::class, addressedTo('juan@example.com'));
    Notification::assertSentOnDemandTimes(ReservationBalanceSettled::class, 0);
});

test('settling an already-settled balance sends nothing', function () {
    $booking = Booking::factory()->for($this->hall)->confirmed()->create();

    $booking->settleBalance();
    Notification::fake();

    expect($booking->settleBalance())->toBeFalse();

    Notification::assertNothingSent();
});

test('room bookings and catering orders raise the same emails', function () {
    $room = Room::factory()->withRates([6 => 1200])->create();
    $package = CateringPackage::factory()->create();

    RoomBooking::factory()->for($room)->create(['guest_email' => 'stay@example.com'])
        ->sendPlacementNotifications();

    CateringOrder::factory()->for($package, 'package')->create(['guest_email' => 'feast@example.com'])
        ->sendPlacementNotifications();

    Notification::assertSentOnDemand(ReservationReceived::class, addressedTo('stay@example.com'));
    Notification::assertSentOnDemand(ReservationReceived::class, addressedTo('feast@example.com'));
});

test('the guest email carries the reference and the dates it covers', function () {
    $booking = Booking::factory()->for($this->hall)->spanningDays(3)->create([
        'start_date' => '2027-09-10',
        'start_hour' => 8,
        'end_hour' => 12,
    ]);

    $booking->sendPlacementNotifications();

    Notification::assertSentOnDemand(
        ReservationReceived::class,
        function ($notification) use ($booking) {
            $summary = $notification->reservation;

            return $summary->reference === $booking->reference
                && $summary->occursAtLabel === 'Sep 10–12, 2027 · 8AM–12PM each day'
                && $summary->total === $booking->total;
        },
    );
});

/**
 * Notification::fake() records notifications without ever calling toMail(), so each email's
 * Blade template needs rendering somewhere or a broken one would sail through every test
 * above.
 */
test('every reservation email renders its template', function (string $notificationClass) {
    $booking = Booking::factory()->for($this->hall)->create([
        'guest_name' => 'Juan dela Cruz',
        'start_date' => '2027-09-10',
    ]);

    $rendered = (string) (new $notificationClass($booking->toSummary()))->toMail($booking)->render();

    expect($rendered)
        ->toContain($booking->reference)
        ->toContain('Function hall')
        ->toContain($this->hall->name);
})->with([
    ReservationReceived::class,
    ReservationBalanceSettled::class,
    ReservationPaymentFailed::class,
    ReservationHoldExpired::class,
    NewReservationAlert::class,
]);

test('each status change is written in its own template', function (BookingStatus $status, string $heading) {
    $booking = Booking::factory()->for($this->hall)->create(['guest_name' => 'Juan dela Cruz']);
    $booking->status = $status;

    $rendered = (string) (new ReservationStatusChanged($booking->toSummary()))->toMail($booking)->render();

    expect($rendered)->toContain($heading)->toContain($booking->reference);
})->with([
    'confirmed' => [BookingStatus::Confirmed, 'Your booking is confirmed'],
    'completed' => [BookingStatus::Completed, 'Your reservation is now complete'],
    'cancelled' => [BookingStatus::Cancelled, 'Your booking has been cancelled'],
]);

test('there is no status email for moving a booking back to pending', function () {
    $booking = Booking::factory()->for($this->hall)->create();

    (new ReservationStatusChanged($booking->toSummary()))->toMail($booking);
})->throws(LogicException::class);

/**
 * Marking a booking done settles whatever was owed, but the summary still carries the
 * balance as it stood at booking. The guest being told their stay is closed must not see
 * that money listed as still owing.
 */
test('the completed email shows the booking as paid in full', function () {
    $booking = Booking::factory()->for($this->hall)->confirmed()->create([
        'guest_name' => 'Juan dela Cruz',
        'start_date' => today()->subWeek()->toDateString(),
    ]);

    expect($booking->balance)->toBeGreaterThan(0);
    expect($booking->transitionTo(BookingStatus::Completed))->toBeTrue();

    $mail = (new ReservationStatusChanged($booking->toSummary()))->toMail($booking);
    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe("Your booking is complete — {$booking->reference}");
    expect($rendered)
        ->toContain('Thank you, Juan dela Cruz')
        ->toContain('We have received your remaining balance of ₱'.number_format($booking->balance))
        ->toMatch('#>Paid</strong></td>\s*<td[^>]*>₱'.number_format($booking->total).'</td>#u')
        ->toMatch('#>Balance</strong></td>\s*<td[^>]*>₱0</td>#u');
});

test('a room email renders the stay rather than a bare hour count', function () {
    $room = Room::factory()->withRates([24 => 2500])->create(['name' => 'Standard Room 101']);
    $booking = RoomBooking::factory()->for($room)->overnight(3)->create();

    $rendered = (string) (new ReservationReceived($booking->toSummary()))->toMail($booking)->render();

    expect($rendered)->toContain('Standard Room 101')->toContain('3 nights');
});

test('an admin moving a booking on from the panel emails the guest', function () {
    $this->actingAs(User::factory()->create());

    $booking = Booking::factory()->for($this->hall)->create(['guest_email' => 'juan@example.com']);

    Livewire\Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, BookingStatus::Confirmed->value);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);

    Notification::assertSentOnDemand(ReservationStatusChanged::class, addressedTo('juan@example.com'));
});

/*
|--------------------------------------------------------------------------
| Resort contact
|--------------------------------------------------------------------------
|
| Staff set the resort's email and number in Settings → Mail. Guests reply to it, and
| every email they receive tells them how to reach it.
|
*/

test('guest email replies go to the resort contact and show how to reach it', function (string $notificationClass) {
    ResortSetting::factory()->create(['contact_email' => 'frontdesk@jehovens.com', 'contact_phone' => '0917 123 4567']);

    $booking = Booking::factory()->for($this->hall)->confirmed()->create();

    $mail = (new $notificationClass($booking->toSummary()))->toMail($booking);

    expect($mail->replyTo)->toBe([['frontdesk@jehovens.com', null]])
        ->and((string) $mail->render())
        ->toContain('Questions?')
        ->toContain('frontdesk@jehovens.com')
        ->toContain('0917 123 4567');
})->with([
    ReservationReceived::class,
    ReservationStatusChanged::class,
    ReservationBalanceSettled::class,
    ReservationPaymentFailed::class,
    ReservationHoldExpired::class,
]);

test('a contact with only a number still tells the guest how to reach the resort', function () {
    ResortSetting::factory()->create(['contact_email' => null, 'contact_phone' => '0917 123 4567']);

    $booking = Booking::factory()->for($this->hall)->create();

    $mail = (new ReservationReceived($booking->toSummary()))->toMail($booking);

    expect($mail->replyTo)->toBe([])
        ->and((string) $mail->render())->toContain('Call or text us at 0917 123 4567');
});

test('without a resort contact, replies go to the sending address and no contact line is shown', function () {
    $booking = Booking::factory()->for($this->hall)->create();

    $mail = (new ReservationReceived($booking->toSummary()))->toMail($booking);

    expect($mail->replyTo)->toBe([])
        ->and((string) $mail->render())->not->toContain('Questions?');
});

test('a reply to the new-booking alert goes to the guest, not back to the resort', function () {
    ResortSetting::factory()->create(['contact_email' => 'frontdesk@jehovens.com']);

    $booking = Booking::factory()->for($this->hall)->create(['guest_email' => 'juan@example.com']);

    $mail = (new NewReservationAlert($booking->toSummary()))->toMail($booking);

    expect($mail->replyTo)->toBe([['juan@example.com', null]])
        ->and((string) $mail->render())->not->toContain('Questions?');
});

test('a guest email still goes out if the resort settings cannot be read', function () {
    Exceptions::fake();
    Schema::drop('resort_settings');

    $booking = Booking::factory()->for($this->hall)->create();

    $rendered = (string) (new ReservationReceived($booking->toSummary()))->toMail($booking)->render();

    expect($rendered)->toContain($booking->reference)->not->toContain('Questions?');
    Exceptions::assertReported(QueryException::class);
});
