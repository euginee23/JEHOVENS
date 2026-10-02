<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Hall;
use App\Models\RoomBooking;
use App\Models\User;
use App\Support\SalesReport;
use Livewire\Livewire;

beforeEach(function () {
    // ₱13,000 total, ₱6,500 down, ₱6,500 balance for every hall booking below.
    $this->hall = Hall::factory()->create(['rent_price' => 8000, 'skirting_price' => 5000]);
    $this->hallBooking = fn (array $attributes = []) => Booking::factory()->for($this->hall)->create([
        'include_skirting' => true,
        'hours' => 4,
        ...$attributes,
    ]);
});

test('the report splits sales, collected and still-to-collect by type', function () {
    ($this->hallBooking)(['status' => BookingStatus::Confirmed]);
    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'balance_settled_at' => now()]);
    ($this->hallBooking)(['status' => BookingStatus::Completed, 'balance_settled_at' => now()]);

    $room = RoomBooking::factory()->confirmed()->create();
    $order = CateringOrder::factory()->confirmed()->create();

    $report = SalesReport::between(now()->startOfMonth(), now()->endOfMonth());

    expect($report['halls'])->toBe([
        'bookings' => 3,
        'sales' => 39_000,
        // Three downpayments plus the two balances recorded as paid.
        'collected' => 3 * 6_500 + 2 * 6_500,
        // Only the unsettled confirmed balance is still owed.
        'toCollect' => 6_500,
    ]);

    expect($report['rooms'])->toBe([
        'bookings' => 1,
        'sales' => $room->total,
        'collected' => $room->amount_paid,
        'toCollect' => $room->balance,
    ]);

    expect($report['catering'])->toBe([
        'bookings' => 1,
        'sales' => $order->total,
        'collected' => $order->downpayment,
        'toCollect' => $order->balance,
    ]);

    expect($report['total']['sales'])->toBe(39_000 + $room->total + $order->total)
        ->and($report['total']['bookings'])->toBe(5);
});

test('pending and cancelled reservations are left out', function () {
    ($this->hallBooking)(['status' => BookingStatus::Pending]);
    ($this->hallBooking)(['status' => BookingStatus::Cancelled]);
    RoomBooking::factory()->create(['status' => BookingStatus::Pending]);
    CateringOrder::factory()->cancelled()->create();

    expect(SalesReport::between(now()->startOfMonth(), now()->endOfMonth())['total'])->toBe([
        'bookings' => 0,
        'sales' => 0,
        'collected' => 0,
        'toCollect' => 0,
    ]);
});

test('the report only counts reservations placed inside the window', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'created_at' => now()->setDate(2026, 5, 31)->endOfDay()]);
    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'created_at' => now()->setDate(2026, 6, 1)->startOfDay()]);
    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'created_at' => now()->setDate(2026, 6, 30)->endOfDay()]);
    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'created_at' => now()->setDate(2026, 7, 1)->startOfDay()]);

    $june = SalesReport::between(now()->startOfMonth(), now()->endOfMonth());

    expect($june['total']['bookings'])->toBe(2);
});

test('the sales page needs a signed-in admin', function () {
    $this->get(route('admin.sales'))->assertRedirect(route('login'));
});

test('the sales page shows the totals for this month by default', function () {
    $this->actingAs(User::factory()->create());

    ($this->hallBooking)(['status' => BookingStatus::Confirmed]);

    $this->get(route('admin.sales'))
        ->assertOk()
        ->assertSee('Gross sales')
        ->assertSee('₱13,000')
        ->assertSee('₱6,500');
});

test('switching the period changes the figures', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(now()->setDate(2026, 6, 15));

    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'created_at' => now()->setDate(2026, 5, 10)]);

    Livewire::test('pages::admin.sales')
        ->assertSet('period', 'this-month')
        ->tap(fn ($component) => expect($component->get('report')['total']['bookings'])->toBe(0))
        ->call('showPeriod', 'last-month')
        ->tap(fn ($component) => expect($component->get('report')['total']['bookings'])->toBe(1))
        ->call('showPeriod', 'this-year')
        ->assertSee('By month')
        ->tap(fn ($component) => expect($component->get('months')['May']['bookings'])->toBe(1));
});

test('a custom range counts what was placed between the two days', function () {
    $this->actingAs(User::factory()->create());

    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'created_at' => now()->subDays(40)]);
    ($this->hallBooking)(['status' => BookingStatus::Confirmed, 'created_at' => now()->subDays(5)]);

    $component = Livewire::test('pages::admin.sales')
        ->call('showPeriod', 'custom')
        ->set('from', now()->subDays(45)->toDateString())
        ->set('until', now()->toDateString());

    expect($component->get('report')['total']['bookings'])->toBe(2);

    // Entered back to front, the range is turned round rather than coming up empty.
    $component->set('from', now()->toDateString())->set('until', now()->subDays(10)->toDateString());

    expect($component->get('report')['total']['bookings'])->toBe(1);
});
