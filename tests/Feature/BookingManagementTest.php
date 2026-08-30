<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->hall = Hall::factory()->create(['name' => 'Grand Ballroom']);
});

test('the bookings page lists reservations', function () {
    Booking::factory()->for($this->hall)->create([
        'guest_name' => 'Juan dela Cruz',
        'guest_phone' => '09171234567',
    ]);

    $this->get(route('admin.bookings'))
        ->assertOk()
        ->assertSee('Juan dela Cruz')
        ->assertSee('Grand Ballroom')
        ->assertSee('09171234567');
});

test('search matches reference, name, phone, and email', function () {
    $target = Booking::factory()->for($this->hall)->create([
        'guest_name' => 'Juan dela Cruz',
        'guest_phone' => '09171234567',
        'guest_email' => 'juan@example.com',
    ]);
    Booking::factory()->for($this->hall)->create(['guest_name' => 'Maria Santos']);

    foreach ([$target->reference, 'Juan', '0917123', 'juan@example'] as $term) {
        $found = Livewire::test('pages::admin.bookings')
            ->set('search', $term)
            ->get('bookings');

        expect($found->pluck('id')->all())->toBe([$target->id], "searching for {$term}");
    }
});

test('bookings can be filtered by status, hall, and date range', function () {
    $other = Hall::factory()->create();

    $pending = Booking::factory()->for($this->hall)->create(['start_date' => '2027-03-10', 'status' => BookingStatus::Pending]);
    Booking::factory()->for($this->hall)->confirmed()->create(['start_date' => '2027-03-20']);
    Booking::factory()->for($other)->create(['start_date' => '2027-03-10']);

    $page = Livewire::test('pages::admin.bookings');

    expect($page->set('status', 'pending')->get('bookings'))->toHaveCount(2);

    $page->set('status', '');
    expect($page->set('venue', (string) $this->hall->id)->get('bookings'))->toHaveCount(2);

    $page->set('venue', '');
    expect($page->set('from', '2027-03-15')->get('bookings'))->toHaveCount(1);

    $page->set('from', '')->set('until', '2027-03-15');
    expect($page->get('bookings')->pluck('id')->all())->toContain($pending->id);
});

test('a booking running across the filter window still shows up in it', function () {
    // Starts before the window and ends after it, so matching on the first day alone
    // would lose it entirely.
    $straddling = Booking::factory()->for($this->hall)->spanningDays(5)->create(['start_date' => '2027-03-08']);

    Booking::factory()->for($this->hall)->create(['start_date' => '2027-04-01']);

    $page = Livewire::test('pages::admin.bookings')
        ->set('from', '2027-03-10')
        ->set('until', '2027-03-11');

    expect($page->get('bookings')->pluck('id')->all())->toBe([$straddling->id]);
});

test('a multi-night stay running across the filter window still shows up in it', function () {
    $room = Room::factory()->withRates([24 => 2500])->create();

    $straddling = RoomBooking::factory()->for($room)->overnight(5)->create([
        'starts_at' => Carbon::parse('2027-03-08')->setTime(14, 0),
    ]);

    $page = Livewire::test('pages::admin.bookings')
        ->call('showType', 'rooms')
        ->set('from', '2027-03-10')
        ->set('until', '2027-03-11');

    expect($page->get('bookings')->pluck('id')->all())->toBe([$straddling->id]);
});

test('the halls and catering tabs list their date ranges', function () {
    Booking::factory()->for($this->hall)->spanningDays(3)->create(['start_date' => '2027-03-10']);

    Livewire::test('pages::admin.bookings')
        ->assertSee('Mar 10–12, 2027')
        ->assertSee('3 days');
});

test('the status chips show how many bookings sit in each state', function () {
    Booking::factory()->for($this->hall)->count(2)->create(['status' => BookingStatus::Pending]);
    Booking::factory()->for($this->hall)->confirmed()->create();
    Booking::factory()->for($this->hall)->cancelled()->create();

    $counts = Livewire::test('pages::admin.bookings')->get('counts');

    expect($counts[''])->toBe(4)
        ->and($counts['pending'])->toBe(2)
        ->and($counts['confirmed'])->toBe(1)
        ->and($counts['completed'])->toBe(0)
        ->and($counts['cancelled'])->toBe(1);
});

test('an admin confirms a pending booking', function () {
    $booking = Booking::factory()->for($this->hall)->create(['status' => BookingStatus::Pending]);

    Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, 'confirmed');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('completing a booking records the balance as settled', function () {
    $booking = Booking::factory()->for($this->hall)->confirmed()->create();

    expect($booking->balance)->toBeGreaterThan(0)
        ->and($booking->balance_settled_at)->toBeNull();

    Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, 'completed');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Completed)
        ->and($booking->balance_settled_at)->not->toBeNull();
});

test('an invalid status move is refused instead of throwing', function () {
    $booking = Booking::factory()->for($this->hall)->create(['status' => BookingStatus::Pending]);

    // Pending must pass through confirmed before it can be completed.
    Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, 'completed')
        ->assertOk();

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending);
});

test('a completed booking cannot be moved anywhere else', function () {
    $booking = Booking::factory()->for($this->hall)->create(['status' => BookingStatus::Completed]);

    foreach (['pending', 'confirmed', 'cancelled'] as $target) {
        Livewire::test('pages::admin.bookings')->call('moveTo', $booking->id, $target);
    }

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed);
});

test('a cancelled booking can be reinstated as pending', function () {
    $booking = Booking::factory()->for($this->hall)->cancelled()->create();

    Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, 'pending');

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending);
});

test('the balance can be recorded as paid, but only once', function () {
    $booking = Booking::factory()->for($this->hall)->confirmed()->create();

    $page = Livewire::test('pages::admin.bookings');

    $page->call('settleBalance', $booking->id);
    $settledAt = $booking->refresh()->balance_settled_at;

    expect($settledAt)->not->toBeNull();

    // A second click must not move the timestamp.
    $page->call('settleBalance', $booking->id);

    expect($booking->refresh()->balance_settled_at->timestamp)->toBe($settledAt->timestamp);
});

test('cancelling frees the slot for a new booking', function () {
    $booking = Booking::factory()->for($this->hall)->create([
        'start_date' => '2027-05-01',
        'start_hour' => 8,
        'end_hour' => 12,
        'status' => BookingStatus::Pending,
    ]);

    expect(Booking::query()->blocking()->count())->toBe(1);

    Livewire::test('pages::admin.bookings')
        ->call('moveTo', $booking->id, 'cancelled');

    expect(Booking::query()->blocking()->count())->toBe(0);
});

test('a completed booking still blocks its slot from being rebooked', function () {
    Booking::factory()->for($this->hall)->create(['status' => BookingStatus::Completed]);

    expect(Booking::query()->blocking()->count())->toBe(1);
});

test('outstanding money excludes settled and cancelled bookings', function () {
    // BookingFactory::configure() recomputes the money columns from the hall's own quote
    // after making, so balances have to be set once the row exists.
    Booking::factory()->for($this->hall)->confirmed()->create()->update(['balance' => 5000]);
    Booking::factory()->for($this->hall)->confirmed()->create()->update(['balance' => 3000, 'balance_settled_at' => now()]);
    Booking::factory()->for($this->hall)->cancelled()->create()->update(['balance' => 9000]);

    expect(Livewire::test('pages::admin.bookings')->get('outstanding'))->toBe(5000);
});

test('clearing filters restores the full list', function () {
    Booking::factory()->for($this->hall)->count(3)->create();

    Livewire::test('pages::admin.bookings')
        ->set('search', 'nothing-matches-this')
        ->assertCount('bookings', 0)
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertCount('bookings', 3);
});

/*
|--------------------------------------------------------------------------
| Rooms tab
|--------------------------------------------------------------------------
*/

test('the rooms tab lists room bookings instead of hall bookings', function () {
    $room = Room::factory()->withRates()->create(['name' => 'Family Room 201']);

    Booking::factory()->for($this->hall)->create(['guest_name' => 'Hall Guest']);
    RoomBooking::factory()->for($room)->create(['guest_name' => 'Room Guest']);

    $page = Livewire::test('pages::admin.bookings');

    expect($page->get('bookings')->pluck('guest_name')->all())->toBe(['Hall Guest']);

    $page->call('showType', 'rooms');

    expect($page->get('bookings')->pluck('guest_name')->all())->toBe(['Room Guest']);

    $page->assertSee('Room Guest')->assertDontSee('Hall Guest');
});

test('the tab counts each type separately', function () {
    $room = Room::factory()->withRates()->create();

    Booking::factory()->for($this->hall)->count(3)->create();
    RoomBooking::factory()->for($room)->count(2)->create();

    $counts = Livewire::test('pages::admin.bookings')->get('typeCounts');

    expect($counts['halls'])->toBe(3)->and($counts['rooms'])->toBe(2);
});

test('status chips and outstanding money follow the active tab', function () {
    $room = Room::factory()->withRates()->create();

    Booking::factory()->for($this->hall)->count(2)->create(['status' => BookingStatus::Pending]);
    RoomBooking::factory()->for($room)->create(['status' => BookingStatus::Pending]);

    $page = Livewire::test('pages::admin.bookings');
    expect($page->get('counts')['pending'])->toBe(2);

    $page->call('showType', 'rooms');
    expect($page->get('counts')['pending'])->toBe(1);
});

test('confirming works on a room booking', function () {
    $room = Room::factory()->withRates()->create();
    $booking = RoomBooking::factory()->for($room)->create(['status' => BookingStatus::Pending]);

    Livewire::test('pages::admin.bookings')
        ->call('showType', 'rooms')
        ->call('moveTo', $booking->id, 'confirmed');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

test('completing a room booking settles its balance', function () {
    $room = Room::factory()->withRates()->create();
    $booking = RoomBooking::factory()->for($room)->confirmed()->create();

    expect($booking->balance)->toBeGreaterThan(0);

    Livewire::test('pages::admin.bookings')
        ->call('showType', 'rooms')
        ->call('moveTo', $booking->id, 'completed');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Completed)
        ->and($booking->balance_settled_at)->not->toBeNull();
});

test('a room booking paid in full has no balance left to settle', function () {
    $room = Room::factory()->withRates()->create();
    $booking = RoomBooking::factory()->for($room)->confirmed()->create();
    $booking->update(['pay_in_full' => true, 'amount_paid' => $booking->total, 'balance' => 0]);

    expect($booking->hasOutstandingBalance())->toBeFalse();

    Livewire::test('pages::admin.bookings')
        ->call('showType', 'rooms')
        ->call('settleBalance', $booking->id);

    expect($booking->refresh()->balance_settled_at)->toBeNull();
});

test('switching tabs clears a venue filter that no longer applies', function () {
    $room = Room::factory()->withRates()->create();
    RoomBooking::factory()->for($room)->create();
    Booking::factory()->for($this->hall)->create();

    Livewire::test('pages::admin.bookings')
        ->set('venue', (string) $this->hall->id)
        ->call('showType', 'rooms')
        ->assertSet('venue', '')
        ->assertCount('bookings', 1);
});

test('the old hall bookings url redirects to the halls tab', function () {
    $response = $this->get('/admin/function-halls/bookings');

    $response->assertRedirect('/admin/bookings?type=halls');

    // assertRedirect normalises both sides, so it would pass on a relative header that a
    // browser resolves against /admin/function-halls/. Check the raw header is absolute.
    expect($response->headers->get('Location'))->toStartWith('/admin/bookings');
});

/*
|--------------------------------------------------------------------------
| Catering tab
|--------------------------------------------------------------------------
*/

test('the catering tab lists orders instead of bookings', function () {
    $package = CateringPackage::factory()->create(['name' => 'Mediterranean Mezze']);

    Booking::factory()->for($this->hall)->create(['guest_name' => 'Hall Guest']);
    CateringOrder::factory()->for($package, 'package')->create(['guest_name' => 'Catering Guest']);

    $page = Livewire::test('pages::admin.bookings')->call('showType', 'catering');

    expect($page->get('bookings')->pluck('guest_name')->all())->toBe(['Catering Guest']);

    $page->assertSee('Catering Guest')
        ->assertSee('Mediterranean Mezze')
        ->assertDontSee('Hall Guest');
});

test('the catering row shows a head count, not a duration', function () {
    // catering_orders has no `hours` column; rendering it unguarded would fatal.
    $package = CateringPackage::factory()->create(['minimum_guests' => 1]);
    CateringOrder::factory()->for($package, 'package')->create(['guests' => 80]);

    Livewire::test('pages::admin.bookings')
        ->call('showType', 'catering')
        ->assertOk()
        ->assertSee('80 guests');
});

test('the tab counts include catering', function () {
    $package = CateringPackage::factory()->create();

    Booking::factory()->for($this->hall)->count(2)->create();
    CateringOrder::factory()->for($package, 'package')->count(3)->create();

    $counts = Livewire::test('pages::admin.bookings')->get('typeCounts');

    expect($counts['halls'])->toBe(2)
        ->and($counts['rooms'])->toBe(0)
        ->and($counts['catering'])->toBe(3);
});

test('catering orders can be filtered by package and event date', function () {
    $mezze = CateringPackage::factory()->create(['name' => 'Mezze']);
    $other = CateringPackage::factory()->create(['name' => 'Other']);

    CateringOrder::factory()->for($mezze, 'package')->create(['start_date' => '2027-07-10']);
    CateringOrder::factory()->for($mezze, 'package')->create(['start_date' => '2027-08-10']);
    CateringOrder::factory()->for($other, 'package')->create(['start_date' => '2027-07-10']);

    $page = Livewire::test('pages::admin.bookings')->call('showType', 'catering');

    expect($page->set('venue', (string) $mezze->id)->get('bookings'))->toHaveCount(2);

    $page->set('venue', '')->set('from', '2027-08-01');
    expect($page->get('bookings'))->toHaveCount(1);
});

test('confirming and completing works on a catering order', function () {
    $package = CateringPackage::factory()->create();
    $order = CateringOrder::factory()->for($package, 'package')->create(['status' => BookingStatus::Pending]);

    $page = Livewire::test('pages::admin.bookings')->call('showType', 'catering');

    $page->call('moveTo', $order->id, 'confirmed');
    expect($order->refresh()->status)->toBe(BookingStatus::Confirmed);

    $page->call('moveTo', $order->id, 'completed');
    $order->refresh();

    expect($order->status)->toBe(BookingStatus::Completed)
        ->and($order->balance_settled_at)->not->toBeNull();
});

test('outstanding money on the catering tab ignores the other types', function () {
    $package = CateringPackage::factory()->create();

    Booking::factory()->for($this->hall)->confirmed()->create()->update(['balance' => 9999]);
    CateringOrder::factory()->for($package, 'package')->confirmed()->create()->update(['balance' => 4000]);

    $page = Livewire::test('pages::admin.bookings')->call('showType', 'catering');

    expect($page->get('outstanding'))->toBe(4000);
});
