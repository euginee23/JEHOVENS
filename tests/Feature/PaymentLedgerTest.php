<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Models\Booking;
use App\Models\Hall;
use App\Models\User;
use Livewire\Livewire;

/**
 * The payments ledger is what sales are counted from, so every way money reaches the
 * resort has to write to it — and none may write to it twice.
 */
beforeEach(function () {
    $this->staff = User::factory()->create(['name' => 'Maria Santos']);
    $this->actingAs($this->staff);

    // ₱13,000 total, ₱6,500 down, ₱6,500 balance.
    $this->hall = Hall::factory()->create(['rent_price' => 8000, 'skirting_price' => 5000]);
    $this->booking = Booking::factory()->for($this->hall)->create([
        'include_skirting' => true,
        'hours' => 4,
        'start_date' => today()->subWeek()->toDateString(),
    ]);
});

test('confirming a booking by hand records the downpayment, credited to the staff member', function () {
    Livewire::test('pages::admin.bookings')->call('moveTo', $this->booking->id, 'confirmed');

    $payment = $this->booking->payments()->sole();

    expect($payment->kind)->toBe(PaymentKind::Downpayment)
        ->and($payment->amount)->toBe(6_500)
        ->and($payment->recorded_by)->toBe($this->staff->id)
        ->and($payment->recordedByLabel())->toBe('Maria Santos')
        ->and($payment->received_at->isToday())->toBeTrue();
});

test('a booking cancelled, reinstated and confirmed again records its downpayment only once', function () {
    $this->booking->transitionTo(BookingStatus::Confirmed);
    $this->booking->transitionTo(BookingStatus::Cancelled);
    $this->booking->transitionTo(BookingStatus::Pending);
    $this->booking->transitionTo(BookingStatus::Confirmed);

    expect($this->booking->payments()->count())->toBe(1);
});

test('recording the balance as paid adds it to the ledger', function () {
    $this->booking->transitionTo(BookingStatus::Confirmed);

    Livewire::test('pages::admin.bookings')->call('settleBalance', $this->booking->id);

    $balance = $this->booking->payments()->where('kind', PaymentKind::Balance)->sole();

    expect($balance->amount)->toBe(6_500)
        ->and($balance->recorded_by)->toBe($this->staff->id)
        ->and($this->booking->payments()->sum('amount'))->toBe(13_000);
});

test('completing a booking that still owed money records the balance', function () {
    $this->booking->transitionTo(BookingStatus::Confirmed);
    $this->booking->transitionTo(BookingStatus::Completed);

    expect($this->booking->payments()->pluck('kind')->all())
        ->toBe([PaymentKind::Downpayment, PaymentKind::Balance]);
});

test('completing a booking whose balance was already recorded adds nothing more', function () {
    $this->booking->transitionTo(BookingStatus::Confirmed);
    $this->booking->settleBalance();
    $this->booking->transitionTo(BookingStatus::Completed);

    expect($this->booking->payments()->count())->toBe(2)
        ->and($this->booking->payments()->sum('amount'))->toBe(13_000);
});

test('the booking detail lists the payments received', function () {
    $this->booking->transitionTo(BookingStatus::Confirmed);

    Livewire::test('pages::admin.bookings')
        ->call('viewBooking', $this->booking->id)
        ->assertSee('Payments received')
        ->assertSee('Downpayment')
        ->assertSee('Maria Santos');
});

test('a booking with nothing received says so', function () {
    Livewire::test('pages::admin.bookings')
        ->call('viewBooking', $this->booking->id)
        ->assertSee('No payment has been received yet.');
});
