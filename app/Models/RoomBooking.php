<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasReservationDates;
use App\Models\Concerns\ManagesReservationLifecycle;
use App\Models\Contracts\Reservation;
use Carbon\CarbonInterface;
use Database\Factories\RoomBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A stay is either day use or an overnight run, and `nights` says which. Zero nights is a
 * day-use booking sold as one of the room's 6/12/24-hour rate blocks; one or more nights
 * is an overnight stay sold at the room's 24-hour rate for each night.
 *
 * The days the room is actually held are listed in `dates()`, and `days` counts them. For
 * an overnight stay those are the nights slept, not the morning the guest checks out: three
 * nights from the 10th lists the 10th, 11th and 12th, and the room is free on the 13th.
 * Day use may cover days that are not consecutive, which is why `starts_at` and `ends_at`
 * are the outer bounds of the stay rather than a promise that everything between is sold.
 *
 * @property int $id
 * @property string $reference
 * @property int $room_id
 * @property int|null $user_id
 * @property string $guest_name
 * @property string $guest_phone
 * @property string $guest_email
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $hours
 * @property int $nights
 * @property int $days
 * @property bool $pay_in_full
 * @property int $total
 * @property int $amount_paid
 * @property int $balance
 * @property CarbonInterface|null $balance_settled_at
 * @property BookingStatus $status
 * @property string|null $payment_provider
 * @property PaymentStatus $payment_status
 * @property string|null $payment_session_id
 * @property string|null $payment_intent_id
 * @property string|null $payment_reference
 * @property string|null $payment_method
 * @property int|null $paid_amount
 * @property Carbon|null $paid_at
 * @property Carbon|null $payment_expires_at
 * @property string|null $admin_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Room $room
 * @property-read User|null $user
 */
#[Fillable([
    'reference', 'room_id', 'user_id', 'guest_name', 'guest_phone', 'guest_email',
    'starts_at', 'ends_at', 'hours', 'nights', 'days', 'pay_in_full', 'total', 'amount_paid', 'balance', 'status',
    'balance_settled_at', 'admin_note',
    'payment_provider', 'payment_status', 'payment_session_id', 'payment_intent_id',
    'payment_reference', 'payment_method', 'paid_amount', 'paid_at', 'payment_expires_at',
])]
class RoomBooking extends Model implements Reservation
{
    /**
     * @use HasFactory<RoomBookingFactory>
     * @use HasReservationDates<RoomBookingDate>
     */
    use HasFactory, HasReservationDates, ManagesReservationLifecycle;

    /**
     * This type keeps its dates in its own table.
     *
     * @return class-string<RoomBookingDate>
     */
    public function dateModel(): string
    {
        return RoomBookingDate::class;
    }

    /**
     * Record how many days the stay covers.
     *
     * Unlike halls and catering, a stay has no `start_date`/`end_date` pair to update:
     * `starts_at` and `ends_at` carry a time of day, which depends on the entry hour and
     * the rate the guest chose, so the booking page sets those itself.
     *
     * @param  array<int, string>  $dates  ISO dates, ascending
     */
    protected function applyDateSpan(array $dates): void
    {
        $this->forceFill(['days' => count($dates)])->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hours' => 'integer',
            'nights' => 'integer',
            'days' => 'integer',
            'pay_in_full' => 'boolean',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'balance' => 'integer',
            'balance_settled_at' => 'datetime',
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'paid_amount' => 'integer',
            'paid_at' => 'datetime',
            'payment_expires_at' => 'datetime',
        ];
    }

    /**
     * The room this booking reserves.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
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
     * Limit the query to bookings that still hold their room.
     *
     * @param  Builder<RoomBooking>  $query
     */
    #[Scope]
    protected function blocking(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::blocking());
    }

    /**
     * Rooms record what the guest paid as the amount paid, which is the whole total
     * when they chose to pay in full.
     */
    public function amountPaidColumn(): string
    {
        return 'amount_paid';
    }

    /**
     * When the guest is asked to arrive, a little ahead of their entry time.
     */
    public function arriveBy(): CarbonInterface
    {
        return $this->starts_at->copy()->subMinutes(Room::ARRIVE_EARLY_MINUTES);
    }

    /**
     * Whether the guest is staying the night rather than booking the room for the day.
     */
    public function isOvernight(): bool
    {
        return $this->nights > 0;
    }

    /**
     * Whether the stay is over.
     *
     * Checkout time, not the last night: completing a booking puts its days back on sale,
     * and the room is not free while the guest is still in it on their final morning.
     */
    public function hasFinished(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * How the stay was sold, for guests reading their confirmation.
     */
    public function stayLabel(): string
    {
        return $this->isOvernight()
            ? trans_choice('{1} :count night|[2,*] :count nights', $this->nights, ['count' => $this->nights])
            : __('Day use');
    }

    /**
     * Generate a booking reference that does not collide with an existing one.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'JGR-R'.Str::upper(Str::random(5));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
