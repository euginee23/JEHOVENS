<?php

namespace App\Models;

use App\Models\Concerns\HasPhotos;
use App\Models\Contracts\Photographable;
use Database\Factories\CateringPackageFactory;
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
 * @property int $price_per_head
 * @property int $skirting_price
 * @property int $minimum_guests
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'price_per_head', 'skirting_price', 'minimum_guests', 'is_active', 'sort_order'])]
class CateringPackage extends Model implements Photographable
{
    /**
     * @use HasFactory<CateringPackageFactory>
     * @use HasPhotos<CateringPackagePhoto>
     */
    use HasFactory, HasPhotos;

    /**
     * The share of the total a guest pays up front to hold the date.
     */
    public const DOWNPAYMENT_RATE = 0.5;

    /**
     * The most guests a single order can be placed for.
     */
    public const MAX_GUESTS = 1000;

    /**
     * This type keeps its photos in its own table.
     *
     * @return class-string<CateringPackagePhoto>
     */
    public function photoModel(): string
    {
        return CateringPackagePhoto::class;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_head' => 'integer',
            'skirting_price' => 'integer',
            'minimum_guests' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The orders placed for this package.
     *
     * @return HasMany<CateringOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(CateringOrder::class);
    }

    /**
     * Limit the query to packages that can currently be ordered.
     *
     * @param  Builder<CateringPackage>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Price an order for the given head count, in whole pesos.
     *
     * The downpayment is rounded up so the resort is never short a peso on an odd total.
     *
     * @return array{catering_total: int, skirting_total: int, total: int, downpayment: int, balance: int}
     */
    public function quote(int $guests, bool $includeSkirting): array
    {
        $cateringTotal = $this->price_per_head * $guests;
        $skirtingTotal = $includeSkirting ? $this->skirting_price : 0;
        $total = $cateringTotal + $skirtingTotal;
        $downpayment = (int) ceil($total * self::DOWNPAYMENT_RATE);

        return [
            'catering_total' => $cateringTotal,
            'skirting_total' => $skirtingTotal,
            'total' => $total,
            'downpayment' => $downpayment,
            'balance' => $total - $downpayment,
        ];
    }
}
