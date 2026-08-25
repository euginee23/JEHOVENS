<?php

namespace App\Livewire;

use App\Models\Contracts\Photographable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Base for the admin screens that manage photos — halls, rooms, and catering packages.
 *
 * A base class rather than a trait: the concrete components are anonymous classes inside
 * Blade single-file components, which static analysis cannot see, so a trait would appear
 * unused. Extending this keeps the shared code analysed.
 */
abstract class ManagesPhotosComponent extends Component
{
    use WithFileUploads;

    /**
     * Freshly picked files, not yet saved.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $uploads = [];

    /**
     * The record whose photos are being managed.
     */
    public ?int $managingPhotos = null;

    /**
     * The model photos are attached to.
     *
     * @return class-string<Model&Photographable>
     */
    abstract protected function photoOwnerModel(): string;

    /**
     * The directory on the public disk these photos live in.
     */
    abstract protected function photoDirectory(): string;

    /**
     * Drop the cached lists so the table and panel both reflect the change.
     */
    abstract protected function refreshAfterPhotoChange(): void;

    /**
     * The record open in the photo modal, for the Blade panel.
     */
    #[Computed]
    public function photoRecord(): ?Photographable
    {
        return $this->loadPhotoRecord();
    }

    /**
     * The same record, fetched directly.
     *
     * The actions below use this rather than `$this->photoRecord`: Livewire resolves the
     * computed property by magic, which static analysis cannot follow.
     */
    protected function loadPhotoRecord(): ?Photographable
    {
        if (! $this->managingPhotos) {
            return null;
        }

        $model = $this->photoOwnerModel();
        $record = $model::query()->with('photos')->find($this->managingPhotos);

        return $record instanceof Photographable ? $record : null;
    }

    /**
     * Open the photo manager for a record.
     */
    public function managePhotos(int $recordId): void
    {
        $this->managingPhotos = $recordId;
        $this->uploads = [];
        $this->resetValidation();

        Flux::modal('photo-manager')->show();
    }

    /**
     * Store the picked files against the record.
     */
    public function uploadPhotos(): void
    {
        $record = $this->loadPhotoRecord();

        if (! $record) {
            return;
        }

        $remaining = $record->remainingPhotoSlots();

        if ($remaining < 1) {
            Flux::toast(variant: 'warning', text: __('This already has the maximum number of photos.'));

            return;
        }

        $this->validate([
            'uploads' => ['required', 'array', 'max:'.$remaining],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'uploads.max' => __('You can add :count more photo(s) here.', ['count' => $remaining]),
            'uploads.*.max' => __('Each photo must be 8MB or smaller.'),
        ]);

        foreach ($this->uploads as $upload) {
            $record->addPhoto($upload, $this->photoDirectory());
        }

        $count = count($this->uploads);
        $this->uploads = [];

        $this->refreshAfterPhotoChange();

        Flux::toast(variant: 'success', text: trans_choice('{1} :count photo added.|[2,*] :count photos added.', $count, ['count' => $count]));
    }

    /**
     * Remove a photo, file and all.
     */
    public function removePhoto(int $photoId): void
    {
        $record = $this->loadPhotoRecord();

        if (! $record) {
            return;
        }

        $record->removePhotoById($photoId);

        $this->refreshAfterPhotoChange();

        Flux::toast(variant: 'success', text: __('Photo removed.'));
    }

    /**
     * Move a photo one place earlier or later in the running order.
     */
    public function movePhoto(int $photoId, string $direction): void
    {
        $record = $this->loadPhotoRecord();

        if (! $record) {
            return;
        }

        $record->movePhotoById($photoId, $direction);

        $this->refreshAfterPhotoChange();
    }
}
