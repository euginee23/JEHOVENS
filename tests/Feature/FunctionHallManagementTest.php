<?php

use App\Models\Booking;
use App\Models\Hall;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the halls page requires signing in', function () {
    auth()->logout();

    $this->get(route('admin.function-halls'))->assertRedirect(route('login'));
    $this->get(route('admin.bookings'))->assertRedirect(route('login'));
});

test('the halls page lists every hall with its booking count', function () {
    $hall = Hall::factory()->create(['name' => 'Grand Ballroom', 'rent_price' => 8000, 'capacity' => 500]);
    Booking::factory()->count(3)->for($hall)->create();
    Hall::factory()->inactive()->create(['name' => 'Closed Wing']);

    $this->get(route('admin.function-halls'))
        ->assertOk()
        ->assertSee('Grand Ballroom')
        ->assertSee('Closed Wing')   // inactive halls stay visible to the admin
        ->assertSee('₱8,000')
        ->assertSee('Bookable')
        ->assertSee('Hidden');
});

test('an admin can add a hall and it appears to guests', function () {
    Livewire::test('pages::admin.function-halls')
        ->call('addHall')
        ->set('name', 'Seaside Pavilion')
        ->set('description', 'Open-air pavilion overlooking the garden, seats 150.')
        ->set('capacity', 150)
        ->set('rent_price', 6000)
        ->set('skirting_price', 3500)
        ->call('save')
        ->assertHasNoErrors();

    $hall = Hall::where('name', 'Seaside Pavilion')->sole();

    expect($hall->slug)->toBe('seaside-pavilion')
        ->and($hall->rent_price)->toBe(6000)
        ->and($hall->is_active)->toBeTrue();

    $this->get(route('booking.function-hall'))->assertSee('Seaside Pavilion');
});

test('editing a hall updates its pricing', function () {
    $hall = Hall::factory()->create(['name' => 'Grand Ballroom', 'rent_price' => 8000]);

    Livewire::test('pages::admin.function-halls')
        ->call('editHall', $hall->id)
        ->assertSet('name', 'Grand Ballroom')
        ->assertSet('rent_price', 8000)
        ->set('rent_price', 9500)
        ->call('save')
        ->assertHasNoErrors();

    expect($hall->refresh()->rent_price)->toBe(9500);
});

test('a hall needs a name, description, capacity, and prices', function () {
    Livewire::test('pages::admin.function-halls')
        ->call('addHall')
        ->call('save')
        ->assertHasErrors(['name', 'description', 'capacity', 'rent_price', 'skirting_price']);

    expect(Hall::count())->toBe(0);
});

test('two halls cannot share a name', function () {
    Hall::factory()->create(['name' => 'Grand Ballroom']);

    Livewire::test('pages::admin.function-halls')
        ->call('addHall')
        ->set('name', 'Grand Ballroom')
        ->set('description', 'A second hall with a clashing name.')
        ->set('capacity', 100)
        ->set('rent_price', 5000)
        ->set('skirting_price', 2000)
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('editing a hall does not trip the unique name rule against itself', function () {
    $hall = Hall::factory()->create(['name' => 'Grand Ballroom']);

    Livewire::test('pages::admin.function-halls')
        ->call('editHall', $hall->id)
        ->set('capacity', 450)
        ->call('save')
        ->assertHasNoErrors();

    expect($hall->refresh()->capacity)->toBe(450);
});

test('hiding a hall removes it from the public page but keeps its bookings', function () {
    $hall = Hall::factory()->create(['name' => 'Grand Ballroom']);
    Booking::factory()->for($hall)->create();

    Livewire::test('pages::admin.function-halls')->call('toggleActive', $hall->id);

    expect($hall->refresh()->is_active)->toBeFalse()
        ->and(Booking::count())->toBe(1);

    $this->get(route('booking.function-hall'))->assertDontSee('Grand Ballroom');
});

test('the database refuses to delete a hall that has bookings', function () {
    $hall = Hall::factory()->create();
    Booking::factory()->for($hall)->create();

    expect(fn () => $hall->delete())->toThrow(QueryException::class);

    expect(Booking::count())->toBe(1)
        ->and(Hall::count())->toBe(1);
});
