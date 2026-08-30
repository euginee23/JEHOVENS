<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Notifications\NewReservationAlert;
use App\Notifications\ReservationBalanceSettled;
use App\Notifications\ReservationReceived;
use App\Notifications\ReservationStatusChanged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

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
 * Notification::fake() records notifications without ever calling toMail(), so the shared
 * Blade template these are all built from needs rendering somewhere or a broken one would
 * sail through every test above.
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
    ReservationStatusChanged::class,
    ReservationBalanceSettled::class,
    NewReservationAlert::class,
]);

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
