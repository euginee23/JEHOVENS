<?php

namespace App\Models;

use App\Models\Concerns\HasPhotos;
use App\Models\Contracts\Photographable;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, RoomRate> $rates
 * @property-read Collection<int, RoomPhoto> $photos
 */
#[Fillable(['name', 'slug', 'description', 'is_active', 'sort_order'])]
class Room extends Model implements Photographable
{
    /**
     * @use HasFactory<RoomFactory>
     * @use HasPhotos<RoomPhoto>
     */
    use HasFactory, HasPhotos;

    /**
     * Earliest hour of the day a guest may check in (24-hour clock).
     */
    public const ENTRY_OPENS_AT = 8;

    /**
     * Latest hour of the day a guest may check in (24-hour clock).
     */
    public const ENTRY_CLOSES_AT = 22;

    /**
     * How many minutes before their entry time guests are asked to arrive.
     */
    public const ARRIVE_EARLY_MINUTES = 30;

    /**
     * The share of the total a guest pays up front when not paying in full.
     */
    public const DOWNPAYMENT_RATE = 0.5;

    /**
     * This type keeps its photos in its own table.
     *
     * @return class-string<RoomPhoto>
     */
    public function photoModel(): string
    {
        return RoomPhoto::class;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The duration options this room can be booked for.
     *
     * @return HasMany<RoomRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class)->orderBy('hours');
    }

    /**
     * The stays booked in this room.
     *
     * @return HasMany<RoomBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    /**
     * Limit the query to rooms that can currently be booked.
     *
     * @param  Builder<Room>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Split a rate into what is due now and what is left to pay on arrival.
     *
     * The amount due is rounded up so the resort is never short a peso on an odd total.
     *
     * @return array{total: int, amount_paid: int, balance: int}
     */
    public function quote(RoomRate $rate, bool $payInFull): array
    {
        $total = $rate->price;
        $amountPaid = $payInFull ? $total : (int) ceil($total * self::DOWNPAYMENT_RATE);

        return [
            'total' => $total,
            'amount_paid' => $amountPaid,
            'balance' => $total - $amountPaid,
        ];
    }
}
