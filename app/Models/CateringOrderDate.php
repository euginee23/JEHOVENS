<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $catering_order_id
 * @property-read CateringOrder $order
 */
#[Fillable(['catering_order_id', 'date'])]
class CateringOrderDate extends ReservationDate
{
    /**
     * The order this date belongs to.
     *
     * @return BelongsTo<CateringOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(CateringOrder::class, 'catering_order_id');
    }
}
