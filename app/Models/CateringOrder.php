<?php

namespace App\Models;

use App\Enums\BookingStatus;
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
 * @property int $id
 * @property string $reference
 * @property int $catering_package_id
 * @property int|null $user_id
 * @property string $guest_name
 * @property string $guest_phone
 * @property string $guest_email
 * @property Carbon $event_date
 * @property int $guests
 * @property bool $include_skirting
 * @property int $price_per_head
 * @property int $catering_total
 * @property int $skirting_total
 * @property int $total
 * @property int $downpayment
 * @property int $balance
 * @property BookingStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CateringPackage $package
 * @property-read User|null $user
 */
#[Fillable([
    'reference', 'catering_package_id', 'user_id', 'guest_name', 'guest_phone', 'guest_email',
    'event_date', 'guests', 'include_skirting', 'price_per_head', 'catering_total',
    'skirting_total', 'total', 'downpayment', 'balance', 'status',
])]
class CateringOrder extends Model
{
    /** @use HasFactory<CateringOrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'guests' => 'integer',
            'include_skirting' => 'boolean',
            'price_per_head' => 'integer',
            'catering_total' => 'integer',
            'skirting_total' => 'integer',
            'total' => 'integer',
            'downpayment' => 'integer',
            'balance' => 'integer',
            'status' => BookingStatus::class,
        ];
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
     * Limit the query to orders the kitchen still has to cook for.
     *
     * @param  Builder<CateringOrder>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
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
