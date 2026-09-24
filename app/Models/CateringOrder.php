<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasReservationDates;
use App\Models\Concerns\ManagesReservationLifecycle;
use App\Models\Contracts\Reservation;
use Carbon\CarbonInterface;
use Database\Factories\CateringOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The days an order is served on are listed in `dates()`, and `days` counts them. They need
 * not be consecutive: `start_date` and `end_date` are only their outer bounds, kept so the
 * admin filters and sorting have one indexed column to work on.
 *
 * The same package is served to the same head count on each day, so `guests` describes a
 * single day: a three-day order for 100 guests is `guests = 100` and `days = 3`, and its
 * `catering_total` already covers all three days.
 *
 * @property int $id
 * @property string $reference
 * @property int $catering_package_id
 * @property int|null $user_id
 * @property string $guest_name
 * @property string $guest_phone
 * @property string $guest_email
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $days
 * @property int $guests
 * @property bool $include_skirting
 * @property int $price_per_head
 * @property int $catering_total
 * @property int $skirting_total
 * @property int $total
 * @property int $downpayment
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
 * @property-read CateringPackage $package
 * @property-read User|null $user
 */
#[Fillable([
    'reference', 'catering_package_id', 'user_id', 'guest_name', 'guest_phone', 'guest_email',
    'start_date', 'end_date', 'days', 'guests', 'include_skirting', 'price_per_head', 'catering_total',
    'skirting_total', 'total', 'downpayment', 'balance', 'status',
    'balance_settled_at', 'admin_note',
    'payment_provider', 'payment_status', 'payment_session_id', 'payment_intent_id',
    'payment_reference', 'payment_method', 'paid_amount', 'paid_at', 'payment_expires_at',
])]
class CateringOrder extends Model implements Reservation
{
    /**
     * @use HasFactory<CateringOrderFactory>
     * @use HasReservationDates<CateringOrderDate>
     */
    use HasFactory, HasReservationDates, ManagesReservationLifecycle;

    /**
     * This type keeps its dates in its own table.
     *
     * @return class-string<CateringOrderDate>
     */
    public function dateModel(): string
    {
        return CateringOrderDate::class;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'guests' => 'integer',
            'include_skirting' => 'boolean',
            'price_per_head' => 'integer',
            'catering_total' => 'integer',
            'skirting_total' => 'integer',
            'total' => 'integer',
            'downpayment' => 'integer',
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
     * Catering records what the guest paid as the downpayment.
     */
    public function amountPaidColumn(): string
    {
        return 'downpayment';
    }

    /**
     * The package this order is for.
     *
     * @return BelongsTo<CateringPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CateringPackage::class, 'catering_package_id');
    }

    /**
     * The account that placed the order, if the guest was signed in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to orders that still stand.
     *
     * @param  Builder<CateringOrder>  $query
     */
    #[Scope]
    protected function blocking(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::blocking());
    }

    /**
     * Generate an order reference that does not collide with an existing one.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'JGR-C'.Str::upper(Str::random(5));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
