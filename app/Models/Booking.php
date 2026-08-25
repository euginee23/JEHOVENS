<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property int $hall_id
 * @property int|null $user_id
 * @property string $guest_name
 * @property string $guest_phone
 * @property string $guest_email
 * @property Carbon $booking_date
 * @property int $start_hour
 * @property int $end_hour
 * @property int $hours
 * @property bool $include_skirting
 * @property int $rent_total
 * @property int $skirting_total
 * @property int $total
 * @property int $downpayment
 * @property int $balance
 * @property BookingStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Hall $hall
 * @property-read User|null $user
 */
#[Fillable([
    'reference', 'hall_id', 'user_id', 'guest_name', 'guest_phone', 'guest_email',
    'booking_date', 'start_hour', 'end_hour', 'hours', 'include_skirting',
    'rent_total', 'skirting_total', 'total', 'downpayment', 'balance', 'status',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'start_hour' => 'integer',
            'end_hour' => 'integer',
            'hours' => 'integer',
            'include_skirting' => 'boolean',
            'rent_total' => 'integer',
            'skirting_total' => 'integer',
            'total' => 'integer',
            'downpayment' => 'integer',
            'balance' => 'integer',
            'status' => BookingStatus::class,
        ];
    }

    /**
     * The hall this booking reserves.
     *
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    /**
     * The account that made the booking, if the guest was signed in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to bookings that still hold their slot.
     *
     * @param  Builder<Booking>  $query
     */
    #[Scope]
    protected function blocking(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::blocking());
    }

    /**
     * Generate a booking reference that does not collide with an existing one.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'JGR-'.Str::upper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
