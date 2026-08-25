<?php

namespace App\Models\Contracts;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;

/**
 * A record guests see photos of — a hall, a room, or a catering package.
 *
 * The reorder and remove operations live behind this contract rather than exposing the
 * relation: Eloquent's HasMany and Collection are invariant in their model type, so an
 * interface cannot usefully hand back `Collection<int, HallPhoto>` as
 * `Collection<int, Photo>`. Keeping the work on the model side avoids that entirely, and
 * is better encapsulation besides.
 */
interface Photographable
{
    /**
     * How many more photos this record can take.
     */
    public function remainingPhotoSlots(): int;

    /**
     * Store an upload and attach it, placing it last in the running order.
     */
    public function addPhoto(UploadedFile $file, string $directory): Photo;

    /**
     * Delete one of this record's photos, file and all.
     *
     * Scoped to this record, so a stale id cannot remove another record's photo.
     */
    public function removePhotoById(int $photoId): void;

    /**
     * Move one of this record's photos a place earlier or later.
     */
    public function movePhotoById(int $photoId, string $direction): void;

    /**
     * The photos as the slideshow component wants them.
     *
     * @return array<int, array{url: string, alt: string, width: int, height: int}>
     */
    public function photoSlides(): array;
}
