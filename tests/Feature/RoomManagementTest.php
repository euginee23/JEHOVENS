<?php

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomPhoto;
use App\Models\User;
use App\Support\PhotoStore;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake(PhotoStore::DISK);
});

test('the rooms page requires signing in', function () {
    auth()->logout();

    $this->get(route('admin.rooms'))->assertRedirect(route('login'));
});

test('the rooms page lists rooms with their rates and photo counts', function () {
    $room = Room::factory()->withRates([6 => 1200, 24 => 2500])->create(['name' => 'Family Room 201']);
    RoomPhoto::factory()->count(2)->for($room)->create();
    Room::factory()->inactive()->create(['name' => 'Under Renovation']);

    $this->get(route('admin.rooms'))
        ->assertOk()
        ->assertSee('Family Room 201')
        ->assertSee('Under Renovation')
        ->assertSee('₱1,200')
        ->assertSee('Bookable')
        ->assertSee('Hidden');
});

test('an admin can add a room with a rate card', function () {
    Livewire::test('pages::admin.rooms')
        ->call('addRoom')
        ->set('name', 'Garden Suite 401')
        ->set('description', 'Quiet suite facing the garden with a double bed and private bath.')
        ->set('rates', [['hours' => 6, 'price' => 1500], ['hours' => 12, 'price' => 2200]])
        ->call('save')
        ->assertHasNoErrors();

    $room = Room::with('rates')->where('name', 'Garden Suite 401')->sole();

    expect($room->slug)->toBe('garden-suite-401')
        ->and($room->rates)->toHaveCount(2)
        ->and($room->rates->pluck('price')->all())->toBe([1500, 2200]);

    $this->get(route('booking.rooms'))->assertSee('Garden Suite 401');
});

test('editing a room replaces its rate card', function () {
    $room = Room::factory()->withRates([6 => 1200, 12 => 1800])->create();

    Livewire::test('pages::admin.rooms')
        ->call('editRoom', $room->id)
        ->assertCount('rates', 2)
        ->set('rates', [['hours' => 24, 'price' => 3000]])
        ->call('save')
        ->assertHasNoErrors();

    expect($room->refresh()->rates->pluck('hours')->all())->toBe([24]);
});

test('a room cannot be saved without at least one rate', function () {
    Livewire::test('pages::admin.rooms')
        ->call('addRoom')
        ->set('name', 'Rateless Room')
        ->set('description', 'A room nobody could ever actually book.')
        ->set('rates', [])
        ->call('save')
        ->assertHasErrors('rates');

    expect(Room::where('name', 'Rateless Room')->exists())->toBeFalse();
});

test('the same duration cannot be listed twice', function () {
    Livewire::test('pages::admin.rooms')
        ->call('addRoom')
        ->set('name', 'Confused Room')
        ->set('description', 'This room lists six hours at two different prices.')
        ->set('rates', [['hours' => 6, 'price' => 1200], ['hours' => 6, 'price' => 1500]])
        ->call('save')
        ->assertHasErrors('rates');

    expect(Room::where('name', 'Confused Room')->exists())->toBeFalse();
});

test('rate rows can be added and removed', function () {
    Livewire::test('pages::admin.rooms')
        ->call('addRoom')
        ->assertCount('rates', 1)
        ->call('addRate')
        ->assertCount('rates', 2)
        ->call('removeRate', 0)
        ->assertCount('rates', 1);
});

test('two rooms cannot share a name', function () {
    Room::factory()->withRates()->create(['name' => 'Family Room 201']);

    Livewire::test('pages::admin.rooms')
        ->call('addRoom')
        ->set('name', 'Family Room 201')
        ->set('description', 'A second room with a clashing name.')
        ->set('rates', [['hours' => 6, 'price' => 1200]])
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('hiding a room removes it from the public page but keeps its bookings', function () {
    $room = Room::factory()->withRates()->create(['name' => 'Family Room 201']);
    RoomBooking::factory()->for($room)->create();

    Livewire::test('pages::admin.rooms')->call('toggleActive', $room->id);

    expect($room->refresh()->is_active)->toBeFalse()
        ->and(RoomBooking::count())->toBe(1);

    $this->get(route('booking.rooms'))->assertDontSee('Family Room 201');
});

test('uploading photos stores resized files and rows', function () {
    $room = Room::factory()->withRates()->create();

    Livewire::test('pages::admin.rooms')
        ->call('managePhotos', $room->id)
        ->set('uploads', [
            UploadedFile::fake()->image('one.jpg', 3000, 2000),
            UploadedFile::fake()->image('two.jpg', 800, 600),
        ])
        ->call('uploadPhotos')
        ->assertHasNoErrors();

    $photos = $room->refresh()->photos;

    expect($photos)->toHaveCount(2)
        ->and($photos->pluck('sort_order')->all())->toBe([1, 2]);

    foreach ($photos as $photo) {
        Storage::disk(PhotoStore::DISK)->assertExists($photo->path);
    }

    // The oversized one was scaled on the way in.
    $first = getimagesizefromstring(Storage::disk(PhotoStore::DISK)->get($photos->first()->path));
    expect($first[0])->toBe(PhotoStore::MAX_WIDTH);
});

test('a room cannot hold more than the maximum number of photos', function () {
    $room = Room::factory()->withRates()->create();
    RoomPhoto::factory()->count(5)->for($room)->create();

    Livewire::test('pages::admin.rooms')
        ->call('managePhotos', $room->id)
        ->set('uploads', [
            UploadedFile::fake()->image('a.jpg', 400, 300),
            UploadedFile::fake()->image('b.jpg', 400, 300),
        ])
        ->call('uploadPhotos')
        ->assertHasErrors('uploads');

    expect($room->refresh()->photos)->toHaveCount(5);
});

test('removing a photo deletes the file, not just the row', function () {
    $room = Room::factory()->withRates()->create();

    Livewire::test('pages::admin.rooms')
        ->call('managePhotos', $room->id)
        ->set('uploads', [UploadedFile::fake()->image('one.jpg', 800, 600)])
        ->call('uploadPhotos');

    $photo = $room->refresh()->photos->sole();
    $path = $photo->path;

    Storage::disk(PhotoStore::DISK)->assertExists($path);

    Livewire::test('pages::admin.rooms')
        ->call('managePhotos', $room->id)
        ->call('removePhoto', $photo->id);

    Storage::disk(PhotoStore::DISK)->assertMissing($path);
    expect(RoomPhoto::find($photo->id))->toBeNull();
});

test('photos can be reordered', function () {
    $room = Room::factory()->withRates()->create();
    $photos = RoomPhoto::factory()->count(3)->for($room)->sequence(
        ['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3],
    )->create();

    Livewire::test('pages::admin.rooms')
        ->call('managePhotos', $room->id)
        ->call('movePhoto', $photos[2]->id, 'up');

    expect($room->refresh()->photos->pluck('id')->all())
        ->toBe([$photos[0]->id, $photos[2]->id, $photos[1]->id]);
});

test('moving the first photo up does nothing', function () {
    $room = Room::factory()->withRates()->create();
    $photos = RoomPhoto::factory()->count(2)->for($room)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();

    Livewire::test('pages::admin.rooms')
        ->call('managePhotos', $room->id)
        ->call('movePhoto', $photos[0]->id, 'up');

    expect($room->refresh()->photos->pluck('id')->all())->toBe([$photos[0]->id, $photos[1]->id]);
});

test('the database refuses to delete a room that has bookings', function () {
    $room = Room::factory()->withRates()->create();
    RoomBooking::factory()->for($room)->create();

    expect(fn () => $room->delete())->toThrow(QueryException::class);

    expect(RoomBooking::count())->toBe(1)->and(Room::count())->toBe(1);
});
