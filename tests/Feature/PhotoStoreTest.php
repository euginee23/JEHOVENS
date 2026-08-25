<?php

use App\Support\PhotoStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(PhotoStore::DISK);
});

/**
 * Build a real JPEG of the given size — UploadedFile::fake()->image() produces one GD can
 * actually read, which is what PhotoStore works on.
 */
function fakePhoto(int $width, int $height): UploadedFile
{
    return UploadedFile::fake()->image('photo.jpg', $width, $height);
}

test('an oversized photo is scaled down to the maximum width', function () {
    $path = PhotoStore::store(fakePhoto(4000, 3000), 'rooms');

    $size = getimagesizefromstring(Storage::disk(PhotoStore::DISK)->get($path));

    expect($size[0])->toBe(PhotoStore::MAX_WIDTH)
        ->and($size[1])->toBe(1200)   // 3000 * 1600/4000, aspect ratio kept
        ->and($path)->toStartWith('rooms/')
        ->and($path)->toEndWith('.jpg');
});

test('a photo already within the limit is not upscaled', function () {
    $path = PhotoStore::store(fakePhoto(900, 600), 'rooms');

    $size = getimagesizefromstring(Storage::disk(PhotoStore::DISK)->get($path));

    expect($size[0])->toBe(900)->and($size[1])->toBe(600);
});

test('a photo exactly at the limit is left alone', function () {
    $path = PhotoStore::store(fakePhoto(PhotoStore::MAX_WIDTH, 900), 'rooms');

    expect(getimagesizefromstring(Storage::disk(PhotoStore::DISK)->get($path))[0])
        ->toBe(PhotoStore::MAX_WIDTH);
});

test('a png is converted to jpeg', function () {
    $path = PhotoStore::store(UploadedFile::fake()->image('shot.png', 800, 600), 'rooms');

    $mime = getimagesizefromstring(Storage::disk(PhotoStore::DISK)->get($path))['mime'];

    expect($mime)->toBe('image/jpeg')->and($path)->toEndWith('.jpg');
});

test('each upload gets its own filename', function () {
    $first = PhotoStore::store(fakePhoto(400, 300), 'rooms');
    $second = PhotoStore::store(fakePhoto(400, 300), 'rooms');

    expect($first)->not->toBe($second);

    Storage::disk(PhotoStore::DISK)->assertExists($first);
    Storage::disk(PhotoStore::DISK)->assertExists($second);
});

test('deleting removes the file from disk', function () {
    $path = PhotoStore::store(fakePhoto(400, 300), 'rooms');

    Storage::disk(PhotoStore::DISK)->assertExists($path);

    PhotoStore::delete($path);

    Storage::disk(PhotoStore::DISK)->assertMissing($path);
});

test('a file that is not an image is rejected', function () {
    expect(fn () => PhotoStore::store(UploadedFile::fake()->create('notes.txt', 10), 'rooms'))
        ->toThrow(RuntimeException::class);
});
