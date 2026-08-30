<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the dashboard renders with the resort branding, not the starter kit', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Recent reservations')
        ->assertSee('View site')
        ->assertDontSee('Platform')
        ->assertDontSee('livewire-starter-kit')
        ->assertDontSee('laravel.com/docs');
});

test('the empty state shows when nothing has been booked', function () {
    Livewire::test('pages::admin.dashboard')
        ->assertSee('No reservations yet')
        ->assertSet('awaitingPayment', 0);
});

test('awaiting payment counts pending reservations across all three types', function () {
    Booking::factory()->count(2)->create(['status' => BookingStatus::Pending]);
    Booking::factory()->confirmed()->create();
    RoomBooking::factory()->create(['status' => BookingStatus::Pending]);
    CateringOrder::factory()->create(['status' => BookingStatus::Pending]);
    CateringOrder::factory()->cancelled()->create();

    expect(Livewire::test('pages::admin.dashboard')->get('awaitingPayment'))->toBe(4);
});

test('confirmed revenue counts only money actually received this month', function () {
    $hall = Hall::factory()->create(['rent_price' => 8000, 'skirting_price' => 5000]);

    // Confirmed: ₱13,000 total, ₱6,500 down.
    Booking::factory()->for($hall)->confirmed()->create(['include_skirting' => true, 'hours' => 4]);

    // Pending money has not arrived, so it must not be counted.
    Booking::factory()->for($hall)->create(['status' => BookingStatus::Pending, 'include_skirting' => true, 'hours' => 4]);

    // Cancelled likewise.
    Booking::factory()->for($hall)->cancelled()->create(['include_skirting' => true, 'hours' => 4]);

    expect(Livewire::test('pages::admin.dashboard')->get('confirmedRevenue'))->toBe(6_500);
});

test('revenue ignores reservations placed before this month', function () {
    Booking::factory()->confirmed()->create(['created_at' => now()->subMonths(2)]);

    expect(Livewire::test('pages::admin.dashboard')->get('confirmedRevenue'))->toBe(0);
});

test('upcoming counts the next seven days and excludes cancelled', function () {
    $room = Room::factory()->withRates([6 => 1200])->create();

    RoomBooking::factory()->for($room)->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(6),
    ]);

    RoomBooking::factory()->for($room)->cancelled()->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(6),
    ]);

    // Outside the window.
    RoomBooking::factory()->for($room)->create([
        'starts_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30)->addHours(6),
    ]);

    Booking::factory()->create(['start_date' => now()->addDay()->toDateString()]);

    expect(Livewire::test('pages::admin.dashboard')->get('upcoming'))->toBe(2);
});

test('the recent list merges all three types, newest first', function () {
    $hall = Booking::factory()->create(['created_at' => now()->subHours(3)]);
    $catering = CateringOrder::factory()->create(['created_at' => now()->subHours(2)]);
    $room = RoomBooking::factory()->create(['created_at' => now()->subHour()]);

    $recent = Livewire::test('pages::admin.dashboard')->get('recent');

    expect($recent->pluck('reference')->all())
        ->toBe([$room->reference, $catering->reference, $hall->reference])
        ->and($recent->pluck('type')->all())
        ->toBe(['Room', 'Catering', 'Function hall']);
});

test('the recent list is capped', function () {
    Booking::factory()->count(15)->create();

    expect(Livewire::test('pages::admin.dashboard')->get('recent'))->toHaveCount(10);
});

test('a reservation shows its reference, guest, and money on the page', function () {
    $hall = Hall::factory()->create(['name' => 'Grand Ballroom', 'rent_price' => 8000, 'skirting_price' => 5000]);

    $booking = Booking::factory()->for($hall)->create([
        'guest_name' => 'Juan dela Cruz',
        'include_skirting' => true,
        'hours' => 4,
        'status' => BookingStatus::Pending,
    ]);

    Livewire::test('pages::admin.dashboard')
        ->assertSee($booking->reference)
        ->assertSee('Juan dela Cruz')
        ->assertSee('Grand Ballroom')
        ->assertSee('₱13,000')
        ->assertSee('₱6,500')
        ->assertSee('Awaiting payment confirmation');
});
