<?php

namespace App\Models;

use Database\Factories\HallPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $hall_id
 * @property-read Hall $hall
 */
#[Fillable(['hall_id', 'path', 'alt', 'sort_order'])]
class HallPhoto extends Photo
{
    /** @use HasFactory<HallPhotoFactory> */
    use HasFactory;

    /**
     * The record this photo belongs to.
     *
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }
}
