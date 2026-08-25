<?php

namespace App\Models;

use Database\Factories\RoomRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $room_id
 * @property int $hours
 * @property int $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Room $room
 */
#[Fillable(['room_id', 'hours', 'price'])]
class RoomRate extends Model
{
    /** @use HasFactory<RoomRateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours' => 'integer',
            'price' => 'integer',
        ];
    }

    /**
     * The room this rate belongs to.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * A label for the duration, e.g. "24 hours (overnight)".
     */
    public function label(): string
    {
        $hours = trans_choice('{1} :count hour|[2,*] :count hours', $this->hours, ['count' => $this->hours]);

        return $this->hours >= 24 ? $hours.' '.__('(overnight)') : $hours;
    }
}
