<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $room_booking_id
 * @property-read RoomBooking $roomBooking
 */
#[Fillable(['room_booking_id', 'date'])]
class RoomBookingDate extends ReservationDate
{
    /**
     * The stay this date belongs to.
     *
     * @return BelongsTo<RoomBooking, $this>
     */
    public function roomBooking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class);
    }
}
