<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Shared base for the three photo tables — `hall_photos`, `room_photos`, and
 * `catering_package_photos`.
 *
 * Each type keeps its own table, but they hold identical columns and behave identically,
 * so the shape lives here once.
 *
 * @property int $id
 * @property string $path
 * @property string|null $alt
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class Photo extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * The public URL for this photo.
     *
     * `asset()` rather than `Storage::url()`: the latter builds from APP_URL, so the
     * images break whenever the app is reached on a host or port that does not match it
     * — a different dev port, or a reverse proxy in front of the server.
     */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
