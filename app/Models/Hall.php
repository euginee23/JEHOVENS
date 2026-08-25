<?php

namespace App\Models;

use App\Models\Concerns\HasPhotos;
use App\Models\Contracts\Photographable;
use Database\Factories\HallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property int $capacity
 * @property int $rent_price
 * @property int $skirting_price
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'capacity', 'rent_price', 'skirting_price', 'is_active', 'sort_order'])]
class Hall extends Model implements Photographable
{
    /**
     * @use HasFactory<HallFactory>
     * @use HasPhotos<HallPhoto>
     */
    use HasFactory, HasPhotos;

    /**
     * The resort rents halls in blocks of this many hours.
     */
    public const HOURS_PER_BLOCK = 4;

    /**
     * Earliest hour of the day a booking may start (24-hour clock).
     */
    public const OPENS_AT = 7;

    /**
     * Latest hour of the day a booking may end (24-hour clock).
     */
    public const CLOSES_AT = 22;

    /**
     * The share of the total a guest pays up front to hold the date.
     */
    public const DOWNPAYMENT_RATE = 0.5;

    /**
     * This type keeps its photos in its own table.
     *
     * @return class-string<HallPhoto>
     */
    public function photoModel(): string
    {
        return HallPhoto::class;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'rent_price' => 'integer',
            'skirting_price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The bookings made against this hall.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Limit the query to halls that can currently be booked.
     *
     * @param  Builder<Hall>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Price a stay of the given length, in whole pesos.
     *
     * The downpayment is rounded up so the resort is never short a peso on an odd total.
     *
     * @return array{blocks: int, rent_total: int, skirting_total: int, total: int, downpayment: int, balance: int}
     */
    public function quote(int $hours, bool $includeSkirting): array
    {
        $blocks = intdiv($hours, self::HOURS_PER_BLOCK);

        $rentTotal = $this->rent_price * $blocks;
        $skirtingTotal = $includeSkirting ? $this->skirting_price : 0;
        $total = $rentTotal + $skirtingTotal;
        $downpayment = (int) ceil($total * self::DOWNPAYMENT_RATE);

        return [
            'blocks' => $blocks,
            'rent_total' => $rentTotal,
            'skirting_total' => $skirtingTotal,
            'total' => $total,
            'downpayment' => $downpayment,
            'balance' => $total - $downpayment,
        ];
    }
}
