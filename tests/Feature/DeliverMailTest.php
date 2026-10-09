<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Hall;
use App\Models\User;
use App\Notifications\ReservationReceived;
use App\Support\DeliverMail;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $booking = Booking::factory()->for(Hall::factory())->create();

    $this->notification = new ReservationReceived($booking->toSummary());
});

/**
 * Point the mailer at a port nothing listens on, so a send fails the way it would with a
 * mail server that is down.
 */
function breakMailer(): void
{
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
    ]);

    Mail::purge('smtp');
}

test('booking email is sent immediately by default', function () {
    Notification::fake();

    $delivered = app(DeliverMail::class)('guest@example.com', $this->notification);

    expect($delivered)->toBeTrue();
    Notification::assertSentOnDemand(
        ReservationReceived::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'guest@example.com',
    );
});

test('booking email reaches the mail transport without a queue worker', function () {
    Queue::fake();
    config(['mail.default' => 'array']);

    app(DeliverMail::class)('guest@example.com', $this->notification);

    Queue::assertNothingPushed();
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});

test('booking email is queued on the configured queue when queued delivery is on', function () {
    Queue::fake();
    config(['mail.default' => 'array', 'resort.mail.queued' => true, 'resort.mail.queue' => 'emails']);

    app(DeliverMail::class)('guest@example.com', $this->notification);

    Queue::assertPushedOn('emails', SendQueuedNotifications::class);
    // Never picked up by a worker before the booking it describes has committed.
    Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->afterCommit === true);
    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
});

test('a failed send is reported instead of thrown', function () {
    Exceptions::fake();
    breakMailer();

    $delivered = app(DeliverMail::class)('guest@example.com', $this->notification);

    expect($delivered)->toBeFalse();
    Exceptions::assertReported(TransportException::class);
});

test('a failed send is remembered for the rest of the request', function () {
    Exceptions::fake();
    breakMailer();

    $deliverMail = app(DeliverMail::class);
    $deliverMail('guest@example.com', $this->notification);

    expect(app(DeliverMail::class)->failures())->toBe(['guest@example.com']);
});

test('a send that worked leaves nothing to warn about', function () {
    config(['mail.default' => 'array']);

    app(DeliverMail::class)('guest@example.com', $this->notification);

    expect(app(DeliverMail::class)->failures())->toBe([]);
});

test('an unreachable mail server gives up after a bounded wait', function () {
    expect(config('mail.mailers.smtp.timeout'))->toBe(10);
});

test('an admin is told when the guest could not be emailed about a change', function () {
    Exceptions::fake();
    breakMailer();

    $this->actingAs(User::factory()->create());
    $booking = Booking::factory()->for(Hall::factory())->create(['guest_email' => 'juan@example.com']);

    Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, BookingStatus::Confirmed->value)
        ->assertDispatched('toast-show', fn ($event, $params) => $params['dataset']['variant'] === 'warning'
            && str_contains($params['slots']['text'], 'the email to the guest could not be sent'));

    // The change itself still went through; only the email failed.
    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('an admin sees the usual confirmation when the guest was emailed', function () {
    config(['mail.default' => 'array']);

    $this->actingAs(User::factory()->create());
    $booking = Booking::factory()->for(Hall::factory())->create();

    Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, BookingStatus::Confirmed->value)
        ->assertDispatched('toast-show', fn ($event, $params) => $params['dataset']['variant'] === 'success');
});
