<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Shared base for the three reservation-date tables — `booking_dates`,
 * `room_booking_dates`, and `catering_order_dates`.
 *
 * Each reservation type keeps its own table, mirroring how the photo tables are arranged,
 * but a date row is a date row, so the shape lives here once.
 *
 * @property int $id
 * @property Carbon $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class ReservationDate extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * This date as the ISO string the calendar and the forms pass around.
     */
    public function iso(): string
    {
        return $this->date->toDateString();
    }
}
