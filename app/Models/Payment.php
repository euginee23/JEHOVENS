<?php

namespace App\Models;

use App\Enums\PaymentKind;
use App\Models\Contracts\Reservation;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Money the resort has actually received against a reservation.
 *
 * Written when a downpayment is verified — by PayMongo or by staff confirming a booking —
 * and when a balance is recorded as paid. What a guest was *asked* to pay stays on the
 * reservation; this is what arrived, and when, which is what sales are counted from.
 *
 * @property int $id
 * @property string $payable_type
 * @property int $payable_id
 * @property PaymentKind $kind
 * @property int $amount
 * @property string|null $method
 * @property string|null $reference
 * @property int|null $recorded_by
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model&Reservation $payable
 * @property-read User|null $recorder
 */
#[Fillable(['kind', 'amount', 'method', 'reference', 'recorded_by', 'received_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'amount' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    /**
     * The hall booking, room booking or catering order this payment was for.
     *
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The staff member who recorded it, or null when PayMongo did.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Who recorded this payment, for the admin lists.
     */
    public function recordedByLabel(): string
    {
        return $this->recorded_by === null ? __('PayMongo') : $this->recorder->name;
    }
}
