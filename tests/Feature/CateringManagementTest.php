<?php

use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\CateringPackagePhoto;
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

test('the catering page requires signing in', function () {
    auth()->logout();

    $this->get(route('admin.catering'))->assertRedirect(route('login'));
});

test('the catering page lists packages with prices and photo counts', function () {
    $package = CateringPackage::factory()->create([
        'name' => 'Mediterranean Mezze',
        'price_per_head' => 450,
        'skirting_price' => 5000,
        'minimum_guests' => 20,
    ]);
    CateringPackagePhoto::factory()->count(2)->for($package)->create();
    CateringPackage::factory()->inactive()->create(['name' => 'Off The Menu']);

    $this->get(route('admin.catering'))
        ->assertOk()
        ->assertSee('Mediterranean Mezze')
        ->assertSee('Off The Menu')   // hidden packages stay visible to the admin
        ->assertSee('₱450')
        ->assertSee('Orderable')
        ->assertSee('Hidden');
});

test('an admin can add a package and guests can order it', function () {
    Livewire::test('pages::admin.catering')
        ->call('addPackage')
        ->set('name', 'Lechon Feast')
        ->set('description', 'Whole roasted lechon with rice, lumpia, and pancit.')
        ->set('price_per_head', 700)
        ->set('skirting_price', 5000)
        ->set('minimum_guests', 30)
        ->call('save')
        ->assertHasNoErrors();

    $package = CateringPackage::where('name', 'Lechon Feast')->sole();

    expect($package->slug)->toBe('lechon-feast')
        ->and($package->price_per_head)->toBe(700)
        ->and($package->minimum_guests)->toBe(30)
        ->and($package->is_active)->toBeTrue();

    $this->get(route('booking.catering'))->assertSee('Lechon Feast');
});

test('editing a package updates its pricing', function () {
    $package = CateringPackage::factory()->create(['name' => 'Mediterranean Mezze', 'price_per_head' => 450]);

    Livewire::test('pages::admin.catering')
        ->call('editPackage', $package->id)
        ->assertSet('price_per_head', 450)
        ->set('price_per_head', 520)
        ->call('save')
        ->assertHasNoErrors();

    expect($package->refresh()->price_per_head)->toBe(520);
});

test('a package needs a name, description, and prices', function () {
    Livewire::test('pages::admin.catering')
        ->call('addPackage')
        ->set('name', '')
        ->set('description', '')
        ->call('save')
        ->assertHasErrors(['name', 'description', 'price_per_head', 'skirting_price']);

    expect(CateringPackage::count())->toBe(0);
});

test('a package cannot be free per head', function () {
    Livewire::test('pages::admin.catering')
        ->call('addPackage')
        ->set('name', 'Free Lunch')
        ->set('description', 'There is no such thing as a free lunch.')
        ->set('price_per_head', 0)
        ->set('skirting_price', 0)
        ->call('save')
        ->assertHasErrors('price_per_head');
});

test('two packages cannot share a name', function () {
    CateringPackage::factory()->create(['name' => 'Mediterranean Mezze']);

    Livewire::test('pages::admin.catering')
        ->call('addPackage')
        ->set('name', 'Mediterranean Mezze')
        ->set('description', 'A second package with a clashing name.')
        ->set('price_per_head', 400)
        ->set('skirting_price', 5000)
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('editing a package does not trip the unique name rule against itself', function () {
    $package = CateringPackage::factory()->create(['name' => 'Mediterranean Mezze']);

    Livewire::test('pages::admin.catering')
        ->call('editPackage', $package->id)
        ->set('minimum_guests', 40)
        ->call('save')
        ->assertHasNoErrors();

    expect($package->refresh()->minimum_guests)->toBe(40);
});

test('hiding a package removes it from the public page but keeps its orders', function () {
    $package = CateringPackage::factory()->create(['name' => 'Mediterranean Mezze']);
    CateringOrder::factory()->for($package, 'package')->create();

    Livewire::test('pages::admin.catering')->call('toggleActive', $package->id);

    expect($package->refresh()->is_active)->toBeFalse()
        ->and(CateringOrder::count())->toBe(1);

    $this->get(route('booking.catering'))->assertDontSee('Mediterranean Mezze');
});

test('uploading photos stores resized files against the package', function () {
    $package = CateringPackage::factory()->create();

    Livewire::test('pages::admin.catering')
        ->call('managePhotos', $package->id)
        ->set('uploads', [
            UploadedFile::fake()->image('spread.jpg', 3000, 2000),
            UploadedFile::fake()->image('dessert.jpg', 800, 600),
        ])
        ->call('uploadPhotos')
        ->assertHasNoErrors();

    $photos = $package->refresh()->photos;

    expect($photos)->toHaveCount(2)
        ->and($photos->pluck('sort_order')->all())->toBe([1, 2]);

    $first = getimagesizefromstring(Storage::disk(PhotoStore::DISK)->get($photos->first()->path));
    expect($first[0])->toBe(PhotoStore::MAX_WIDTH);
});

test('removing a package photo deletes the file', function () {
    $package = CateringPackage::factory()->create();

    Livewire::test('pages::admin.catering')
        ->call('managePhotos', $package->id)
        ->set('uploads', [UploadedFile::fake()->image('spread.jpg', 800, 600)])
        ->call('uploadPhotos');

    $photo = $package->refresh()->photos->sole();

    Livewire::test('pages::admin.catering')
        ->call('managePhotos', $package->id)
        ->call('removePhoto', $photo->id);

    Storage::disk(PhotoStore::DISK)->assertMissing($photo->path);
    expect(CateringPackagePhoto::find($photo->id))->toBeNull();
});

test('package photos can be reordered', function () {
    $package = CateringPackage::factory()->create();
    $photos = CateringPackagePhoto::factory()->count(3)->for($package)->sequence(
        ['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3],
    )->create();

    Livewire::test('pages::admin.catering')
        ->call('managePhotos', $package->id)
        ->call('movePhoto', $photos[2]->id, 'up');

    expect($package->refresh()->photos->pluck('id')->all())
        ->toBe([$photos[0]->id, $photos[2]->id, $photos[1]->id]);
});

test('a package cannot exceed the photo limit', function () {
    $package = CateringPackage::factory()->create();
    CateringPackagePhoto::factory()->count(CateringPackage::PHOTO_LIMIT)->for($package)->create();

    Livewire::test('pages::admin.catering')
        ->call('managePhotos', $package->id)
        ->set('uploads', [UploadedFile::fake()->image('extra.jpg', 400, 300)])
        ->call('uploadPhotos');

    expect($package->refresh()->photos)->toHaveCount(CateringPackage::PHOTO_LIMIT);
});

test('the database refuses to delete a package that has orders', function () {
    $package = CateringPackage::factory()->create();
    CateringOrder::factory()->for($package, 'package')->create();

    expect(fn () => $package->delete())->toThrow(QueryException::class);

    expect(CateringOrder::count())->toBe(1)->and(CateringPackage::count())->toBe(1);
});
