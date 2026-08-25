<?php

namespace App\Models;

use Database\Factories\RoomPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $room_id
 * @property-read Room $room
 */
#[Fillable(['room_id', 'path', 'alt', 'sort_order'])]
class RoomPhoto extends Photo
{
    /** @use HasFactory<RoomPhotoFactory> */
    use HasFactory;

    /**
     * The room this photo belongs to.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
