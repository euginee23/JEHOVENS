<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\ManagesReservationLifecycle;
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
 * is an overnight stay sold at the room's 24-hour rate for each night. Either way
 * `starts_at` and `ends_at` bound the whole stay and `hours` is its full length.
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
 * @property bool $pay_in_full
 * @property int $total
 * @property int $amount_paid
 * @property int $balance
 * @property CarbonInterface|null $balance_settled_at
 * @property BookingStatus $status
 * @property string|null $admin_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Room $room
 * @property-read User|null $user
 */
#[Fillable([
    'reference', 'room_id', 'user_id', 'guest_name', 'guest_phone', 'guest_email',
    'starts_at', 'ends_at', 'hours', 'nights', 'pay_in_full', 'total', 'amount_paid', 'balance', 'status',
    'balance_settled_at', 'admin_note',
])]
class RoomBooking extends Model
{
    /** @use HasFactory<RoomBookingFactory> */
    use HasFactory, ManagesReservationLifecycle;

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
            'pay_in_full' => 'boolean',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'balance' => 'integer',
            'balance_settled_at' => 'datetime',
            'status' => BookingStatus::class,
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
