<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Concerns\ManagesReservationLifecycle;
use App\Models\RoomBooking;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What the resort has sold and collected over a stretch of time.
 *
 * The one place the revenue rule lives, so the dashboard tile and the Sales page cannot
 * drift apart. Only Confirmed and Completed reservations count: a pending one is money
 * not yet verified, and a cancelled one is not going ahead. Completed has to be in here —
 * leaving it out made a booking's money vanish from revenue the moment staff marked it
 * done, which is the opposite of what finishing a booking means.
 *
 * Reservations are counted by when they were placed, as the dashboard always has.
 *
 * @phpstan-type SalesRow array{bookings: int, sales: int, collected: int, toCollect: int}
 */
final class SalesReport
{
    /**
     * Sales per reservation type and in total, for reservations placed in the window.
     *
     * - `sales`: the full value of what was booked
     * - `collected`: the downpayments plus any balances recorded as paid
     * - `toCollect`: balances still owed on bookings that are going ahead
     *
     * The tables disagree on the name of the column holding what the guest paid up front,
     * as {@see ManagesReservationLifecycle} explains, so each type names its own.
     *
     * @return array{halls: SalesRow, rooms: SalesRow, catering: SalesRow, total: SalesRow}
     */
    public static function between(CarbonInterface $from, CarbonInterface $until): array
    {
        $rows = [
            'halls' => self::summarise(Booking::query(), 'coalesce(sum(downpayment), 0) as paid', $from, $until),
            'rooms' => self::summarise(RoomBooking::query(), 'coalesce(sum(amount_paid), 0) as paid', $from, $until),
            'catering' => self::summarise(CateringOrder::query(), 'coalesce(sum(downpayment), 0) as paid', $from, $until),
        ];

        $rows['total'] = [
            'bookings' => array_sum(array_column($rows, 'bookings')),
            'sales' => array_sum(array_column($rows, 'sales')),
            'collected' => array_sum(array_column($rows, 'collected')),
            'toCollect' => array_sum(array_column($rows, 'toCollect')),
        ];

        return $rows;
    }

    /**
     * One reservation type's figures, in a single query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $paidSum  the select summing this type's paid column, as `paid`
     * @return SalesRow
     */
    private static function summarise(Builder $query, string $paidSum, CarbonInterface $from, CarbonInterface $until): array
    {
        $totals = $query
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed])
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $until)
            ->toBase()
            ->selectRaw('count(*) as bookings')
            ->selectRaw('coalesce(sum(total), 0) as sales')
            ->selectRaw($paidSum)
            ->selectRaw('coalesce(sum(case when balance_settled_at is not null then balance else 0 end), 0) as settled')
            ->selectRaw('coalesce(sum(case when status = ? and balance_settled_at is null then balance else 0 end), 0) as owed', [BookingStatus::Confirmed->value])
            ->first();

        return [
            'bookings' => (int) $totals->bookings,
            'sales' => (int) $totals->sales,
            'collected' => (int) $totals->paid + (int) $totals->settled,
            'toCollect' => (int) $totals->owed,
        ];
    }
}
