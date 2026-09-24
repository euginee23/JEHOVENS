<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $booking_id
 * @property-read Booking $booking
 */
#[Fillable(['booking_id', 'date'])]
class BookingDate extends ReservationDate
{
    /**
     * The booking this date belongs to.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
