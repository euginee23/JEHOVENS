<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Writing a run of days out in as few words as it takes.
 *
 * Bookings, orders and stays all span a range now, and that range is shown on the booking
 * pages, in the admin tables, and in every email. Keeping the formatting here means all
 * three read the same, and a single-day booking never reads as an awkward "Sep 10–10".
 */
final class DateRange
{
    /**
     * The range written out in full, e.g. "September 10–12, 2026".
     */
    public static function label(CarbonInterface $from, CarbonInterface $until): string
    {
        return self::format($from, $until, 'F j', 'F j, Y');
    }

    /**
     * The range abbreviated for table cells, e.g. "Sep 10–12, 2026".
     */
    public static function shortLabel(CarbonInterface $from, CarbonInterface $until): string
    {
        return self::format($from, $until, 'M j', 'M j, Y');
    }

    /**
     * Every ISO date from one moment to another, inclusive of both ends.
     *
     * The cursor is reassigned rather than advanced in place: this application sets
     * `Date::use(CarbonImmutable::class)`, so the dates handed in by an Eloquent cast are
     * immutable and `$cursor->addDay()` on its own would never move.
     *
     * @return array<int, string>
     */
    public static function daysBetween(CarbonInterface $from, CarbonInterface $until): array
    {
        $dates = [];
        $cursor = $from->copy()->startOfDay();
        $last = $until->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    /**
     * Build the range, dropping whatever the two ends already share.
     */
    private static function format(CarbonInterface $from, CarbonInterface $until, string $partial, string $full): string
    {
        if ($from->isSameDay($until)) {
            return $from->format($full);
        }

        if (! $from->isSameYear($until)) {
            return $from->format($full).' – '.$until->format($full);
        }

        // Within one month only the day number needs repeating: "September 10–12, 2026".
        if ($from->isSameMonth($until)) {
            return $from->format($partial).'–'.$until->format('j, Y');
        }

        return $from->format($partial).' – '.$until->format($full);
    }
}
