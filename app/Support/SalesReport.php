<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Payment;
use App\Models\RoomBooking;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the resort has actually collected, read from the payments ledger.
 *
 * The one place the sales rule lives, so the dashboard tile and the Sales page cannot
 * drift apart. A sale is money received — a verified downpayment or a balance recorded as
 * paid — counted on the day it arrived, not the day the booking was placed. That is what
 * lets the resort say what came in today, and it means a booking's money stays counted
 * whatever happens to its status afterwards.
 *
 * @phpstan-type ModuleTotals array{halls: int, rooms: int, catering: int, total: int}
 */
final class SalesReport
{
    /**
     * The modules sales are split by, keyed as the admin pages key them.
     *
     * @var array<string, class-string>
     */
    public const MODULES = [
        'halls' => Booking::class,
        'rooms' => RoomBooking::class,
        'catering' => CateringOrder::class,
    ];

    /**
     * Money collected per module and in total, optionally within a window.
     *
     * @return ModuleTotals
     */
    public static function collected(?CarbonInterface $from = null, ?CarbonInterface $until = null): array
    {
        $sums = self::payments($from, $until)
            ->toBase()
            ->selectRaw('payable_type, coalesce(sum(amount), 0) as collected')
            ->groupBy('payable_type')
            ->pluck('collected', 'payable_type');

        return [
            'halls' => (int) ($sums[Booking::class] ?? 0),
            'rooms' => (int) ($sums[RoomBooking::class] ?? 0),
            'catering' => (int) ($sums[CateringOrder::class] ?? 0),
            'total' => (int) $sums->sum(),
        ];
    }

    /**
     * Money collected in each day, month or year of a window, oldest first.
     *
     * Bucketed in PHP rather than with a SQL date function, which MySQL and the SQLite
     * the tests run on spell differently. A resort's payments for a year fit comfortably.
     *
     * @param  'day'|'month'|'year'  $unit
     * @return array<string, int> amount collected, keyed by the start of each bucket as Y-m-d
     */
    public static function trend(CarbonInterface $from, CarbonInterface $until, string $unit): array
    {
        $buckets = [];
        $cursor = CarbonImmutable::instance($from)->startOf($unit);

        while ($cursor->lessThanOrEqualTo($until)) {
            $buckets[$cursor->toDateString()] = 0;
            $cursor = $cursor->add(1, $unit);
        }

        foreach (self::payments($from, $until)->get(['amount', 'received_at']) as $payment) {
            $key = $payment->received_at->startOf($unit)->toDateString();

            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += $payment->amount;
            }
        }

        return $buckets;
    }

    /**
     * Balances still owed on bookings that are going ahead, whenever they were placed.
     */
    public static function stillToCollect(): int
    {
        $owed = 0;

        foreach ([Booking::query(), RoomBooking::query(), CateringOrder::query()] as $query) {
            $owed += (int) $query
                ->where('status', BookingStatus::Confirmed)
                ->whereNull('balance_settled_at')
                ->sum('balance');
        }

        return $owed;
    }

    /**
     * The payments received within a window, or all of them.
     *
     * @return Builder<Payment>
     */
    public static function payments(?CarbonInterface $from = null, ?CarbonInterface $until = null): Builder
    {
        return Payment::query()
            ->when($from, fn (Builder $query) => $query->where('received_at', '>=', $from))
            ->when($until, fn (Builder $query) => $query->where('received_at', '<=', $until));
    }

    /**
     * Which module a payment belongs to, from the reservation it was for.
     */
    public static function moduleOf(Payment $payment): string
    {
        $module = array_search($payment->payable_type, self::MODULES, strict: true);

        return is_string($module) ? $module : 'halls';
    }
}
