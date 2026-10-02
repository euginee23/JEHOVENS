<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Payment;
use App\Models\RoomBooking;
use App\Models\User;
use App\Support\SalesReport;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/**
 * A payment of the given amount against a reservation, received at the given moment.
 */
function receivedPayment(Model $reservation, int $amount, ?DateTimeInterface $at = null): Payment
{
    return Payment::factory()->for($reservation, 'payable')->create([
        'amount' => $amount,
        'received_at' => $at ?? now(),
    ]);
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 6, 15)->setTime(10, 0));
});

test('collected splits the money received by module', function () {
    receivedPayment(Booking::factory()->confirmed()->create(), 6_500);
    receivedPayment(Booking::factory()->confirmed()->create(), 3_000);
    receivedPayment(RoomBooking::factory()->confirmed()->create(), 1_200);
    receivedPayment(CateringOrder::factory()->confirmed()->create(), 9_000);

    expect(SalesReport::collected())->toBe([
        'halls' => 9_500,
        'rooms' => 1_200,
        'catering' => 9_000,
        'total' => 19_700,
    ]);
});

/**
 * A sale is counted the day the money arrived. A booking placed in May whose balance
 * came in during June is part of June's sales for that balance.
 */
test('collected counts payments by when they were received, not when the booking was placed', function () {
    $booking = Booking::factory()->confirmed()->create(['created_at' => now()->subMonth()]);

    receivedPayment($booking, 6_500, now()->subMonth());
    receivedPayment($booking, 6_500, now());

    expect(SalesReport::collected(now()->startOfMonth(), now()->endOfMonth())['total'])->toBe(6_500)
        ->and(SalesReport::collected()['total'])->toBe(13_000);
});

test('the window includes both of its edges and nothing past them', function () {
    $booking = Booking::factory()->confirmed()->create();

    receivedPayment($booking, 1, now()->setDate(2026, 5, 31)->endOfDay());
    receivedPayment($booking, 10, now()->setDate(2026, 6, 1)->startOfDay());
    receivedPayment($booking, 100, now()->setDate(2026, 6, 30)->endOfDay());
    receivedPayment($booking, 1_000, now()->setDate(2026, 7, 1)->startOfDay());

    expect(SalesReport::collected(now()->startOfMonth(), now()->endOfMonth())['total'])->toBe(110);
});

test('money stays counted when its booking is later cancelled', function () {
    $booking = Booking::factory()->confirmed()->create();
    receivedPayment($booking, 6_500);

    $booking->transitionTo(BookingStatus::Cancelled);

    expect(SalesReport::collected()['total'])->toBe(6_500);
});

test('the trend fills every day of the window, including the empty ones', function () {
    $booking = Booking::factory()->confirmed()->create();

    receivedPayment($booking, 500, now()->setDate(2026, 6, 3)->setTime(9, 0));
    receivedPayment($booking, 700, now()->setDate(2026, 6, 3)->setTime(17, 0));
    receivedPayment($booking, 900, now()->setDate(2026, 6, 20));

    $trend = SalesReport::trend(now()->startOfMonth(), now()->endOfMonth(), 'day');

    expect($trend)->toHaveCount(30)
        ->and($trend['2026-06-03'])->toBe(1_200)
        ->and($trend['2026-06-20'])->toBe(900)
        ->and($trend['2026-06-04'])->toBe(0);
});

test('the trend can be drawn by month', function () {
    $booking = Booking::factory()->confirmed()->create();

    receivedPayment($booking, 500, now()->setDate(2026, 2, 10));
    receivedPayment($booking, 700, now()->setDate(2026, 2, 25));

    $trend = SalesReport::trend(now()->startOfYear(), now()->endOfYear(), 'month');

    expect($trend)->toHaveCount(12)
        ->and($trend['2026-02-01'])->toBe(1_200)
        ->and($trend['2026-03-01'])->toBe(0);
});

test('still to collect sums unsettled balances on confirmed bookings only', function () {
    $owing = Booking::factory()->confirmed()->create();
    Booking::factory()->confirmed()->create(['balance_settled_at' => now()]);
    Booking::factory()->create(['status' => BookingStatus::Pending]);
    Booking::factory()->cancelled()->create();

    expect(SalesReport::stillToCollect())->toBe($owing->balance);
});

test('the sales page needs a signed-in admin', function () {
    $this->get(route('admin.sales'))->assertRedirect(route('login'));
});

test('the sales page shows today, this month, this year and all time', function () {
    $this->actingAs(User::factory()->create());

    $booking = Booking::factory()->confirmed()->create();

    receivedPayment($booking, 1_000, now());
    receivedPayment($booking, 2_000, now()->setDate(2026, 6, 2));
    receivedPayment($booking, 4_000, now()->setDate(2026, 2, 2));
    receivedPayment($booking, 8_000, now()->setDate(2025, 12, 2));

    Livewire::test('pages::admin.sales')
        ->assertSet('headline', [
            'total' => 15_000,
            'today' => 1_000,
            'month' => 3_000,
            'year' => 7_000,
        ])
        ->assertSee("Today's sales")
        ->assertSee('Sales per module')
        ->assertSee('Daily sales');
});

test('the period changes the breakdown and the payments listed', function () {
    $this->actingAs(User::factory()->create());

    $booking = Booking::factory()->confirmed()->create(['guest_name' => 'Juan dela Cruz']);
    receivedPayment($booking, 2_500, now()->setDate(2026, 3, 10));

    Livewire::test('pages::admin.sales')
        ->assertSet('modules.total', 0)
        ->assertSee('No payments received in this period.')
        ->call('showPeriod', 'this-year')
        ->assertSet('modules.halls', 2_500)
        ->assertSee('Monthly sales')
        ->assertSee($booking->reference)
        ->assertSee('Juan dela Cruz')
        ->call('showPeriod', 'today')
        ->assertSet('modules.total', 0);
});

test('a custom range is read in either order', function () {
    $this->actingAs(User::factory()->create());

    receivedPayment(Booking::factory()->confirmed()->create(), 2_500, now()->subDays(5));

    Livewire::test('pages::admin.sales')
        ->call('showPeriod', 'custom')
        ->set('from', now()->subDays(10)->toDateString())
        ->set('until', now()->toDateString())
        ->assertSet('modules.total', 2_500)
        ->set('from', now()->toDateString())
        ->set('until', now()->subDays(10)->toDateString())
        ->assertSet('modules.total', 2_500)
        ->set('until', 'not-a-date')
        ->assertOk();
});
