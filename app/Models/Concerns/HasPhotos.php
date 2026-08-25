<?php

namespace App\Models\Concerns;

use App\Models\Photo;
use App\Support\PhotoStore;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

/**
 * Photo handling shared by halls, rooms, and catering packages.
 *
 * Each type keeps its own table — `hall_photos`, `room_photos`,
 * `catering_package_photos` — so the using model names its photo class. Only the
 * behaviour is shared, not the schema.
 *
 * @template TPhoto of Photo
 */
trait HasPhotos
{
    /**
     * The most photos one record may carry.
     */
    public const PHOTO_LIMIT = 6;

    /**
     * The model holding this type's photos.
     *
     * @return class-string<TPhoto>
     */
    abstract public function photoModel(): string;

    /**
     * The photos shown to guests, in display order.
     *
     * @return HasMany<TPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany($this->photoModel())->orderBy('sort_order')->orderBy('id');
    }

    /**
     * How many more photos this record can take.
     */
    public function remainingPhotoSlots(): int
    {
        return max(0, self::PHOTO_LIMIT - $this->photos()->count());
    }

    /**
     * Store an upload and attach it, placing it last in the running order.
     */
    /**
     * @return TPhoto
     */
    public function addPhoto(UploadedFile $file, string $directory): Photo
    {
        return $this->photos()->create([
            'path' => PhotoStore::store($file, $directory),
            'alt' => __(':name at Jehoven\'s Garden Resort', ['name' => $this->name]),
            'sort_order' => (int) $this->photos()->max('sort_order') + 1,
        ]);
    }

    /**
     * Delete one of this record's photos, file and all.
     */
    public function removePhotoById(int $photoId): void
    {
        $photo = $this->photos()->findOrFail($photoId);

        PhotoStore::delete($photo->path);
        $photo->delete();

        $this->resequencePhotos();
    }

    /**
     * Move one of this record's photos a place earlier or later.
     */
    public function movePhotoById(int $photoId, string $direction): void
    {
        $photos = $this->photos()->get()->values();
        $index = $photos->search(fn (Photo $photo) => $photo->id === $photoId);

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $photos->count()) {
            return;
        }

        $reordered = $photos->all();
        [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

        foreach ($reordered as $position => $photo) {
            $photo->update(['sort_order' => $position + 1]);
        }
    }

    /**
     * Rewrite this record's photo order as 1..n, so there are never gaps or ties.
     */
    protected function resequencePhotos(): void
    {
        foreach ($this->photos()->get()->values() as $position => $photo) {
            $photo->update(['sort_order' => $position + 1]);
        }
    }

    /**
     * The photos as the slideshow component wants them.
     *
     * @return array<int, array{url: string, alt: string, width: int, height: int}>
     */
    public function photoSlides(): array
    {
        return $this->photos->map(fn (Photo $photo) => [
            'url' => $photo->url(),
            'alt' => (string) $photo->alt,
            'width' => 1600,
            'height' => 1200,
        ])->all();
    }
}
