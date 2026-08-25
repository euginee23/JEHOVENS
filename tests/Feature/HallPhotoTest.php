<?php

use App\Models\Hall;
use App\Models\HallPhoto;
use App\Models\User;
use App\Support\PhotoStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake(PhotoStore::DISK);
});

test('a hall accepts photos through the shared panel', function () {
    $hall = Hall::factory()->create();

    Livewire::test('pages::admin.function-halls')
        ->call('managePhotos', $hall->id)
        ->set('uploads', [UploadedFile::fake()->image('hall.jpg', 4000, 3000)])
        ->call('uploadPhotos')
        ->assertHasNoErrors();

    $photo = $hall->refresh()->photos->sole();

    Storage::disk(PhotoStore::DISK)->assertExists($photo->path);

    expect($photo->path)->toStartWith('function-hall/')
        ->and(getimagesizefromstring(Storage::disk(PhotoStore::DISK)->get($photo->path))[0])
        ->toBe(PhotoStore::MAX_WIDTH);
});

test('hall photos can be reordered and removed', function () {
    $hall = Hall::factory()->create();
    $photos = HallPhoto::factory()->count(3)->for($hall)->sequence(
        ['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3],
    )->create();

    $page = Livewire::test('pages::admin.function-halls')->call('managePhotos', $hall->id);

    $page->call('movePhoto', $photos[0]->id, 'down');
    expect($hall->refresh()->photos->pluck('id')->all())
        ->toBe([$photos[1]->id, $photos[0]->id, $photos[2]->id]);

    $page->call('removePhoto', $photos[0]->id);
    expect($hall->refresh()->photos)->toHaveCount(2);
});

test('a hall photo cannot be removed through another hall', function () {
    $mine = Hall::factory()->create();
    $theirs = Hall::factory()->create();
    $photo = HallPhoto::factory()->for($theirs)->create();

    // The panel is open on $mine, so a stale id for another hall must not delete anything.
    expect(fn () => Livewire::test('pages::admin.function-halls')
        ->call('managePhotos', $mine->id)
        ->call('removePhoto', $photo->id))
        ->toThrow(ModelNotFoundException::class);

    expect(HallPhoto::find($photo->id))->not->toBeNull();
});

test('a hall with photos shows them on the booking page without dots', function () {
    $hall = Hall::factory()->create(['name' => 'Grand Ballroom']);
    HallPhoto::factory()->count(2)->for($hall)->sequence(
        ['path' => 'function-hall/one.jpg'],
        ['path' => 'function-hall/two.jpg'],
    )->create();

    $html = $this->get(route('booking.function-hall'))->assertOk()->getContent();

    expect($html)->toContain('function-hall/one.jpg')
        ->and($html)->toContain('function-hall/two.jpg');

    $card = str($html)->after('wire:key="hall-'.$hall->id.'"')->before('</button>')->toString();

    expect($card)->toContain('x-data')
        ->and($card)->not->toContain('Show photo')   // the dots' screen-reader label
        ->and($card)->not->toContain('<button');     // no nested interactive element
});

test('a hall without photos still renders its card', function () {
    Hall::factory()->create(['name' => 'Plain Hall']);

    $this->get(route('booking.function-hall'))->assertOk()->assertSee('Plain Hall');
});
