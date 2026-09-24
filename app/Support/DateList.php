<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Writing out a set of chosen days, which need not be consecutive.
 *
 * Guests pick the days they want one at a time, so a booking may cover the 9th, the 15th
 * and the 20th and nothing in between. {@see DateRange} can only describe a run from one
 * day to another, and using it here would tell a guest they had booked eleven days when
 * they had booked three.
 *
 * An unbroken run is still handed to DateRange, so the common case reads exactly as it
 * always has and nothing that only ever books consecutive days sees a change.
 */
final class DateList
{
    /**
     * The days written out in full, e.g. "September 9, 15 & 20, 2026".
     *
     * Never truncated — an email has to list every day the guest is paying for.
     *
     * @param  array<int, string>  $dates  ISO dates, in any order
     */
    public static function label(array $dates): string
    {
        return self::format($dates, short: false, max: null);
    }

    /**
     * The days abbreviated for table cells, e.g. "Sep 9, 15 & 20, 2026".
     *
     * Past `$max` days the rest are summarised, so one guest booking a month of Saturdays
     * cannot stretch a column out of shape.
     *
     * @param  array<int, string>  $dates  ISO dates, in any order
     */
    public static function shortLabel(array $dates, int $max = 5): string
    {
        return self::format($dates, short: true, max: $max);
    }

    /**
     * Whether the given days form an unbroken run.
     *
     * @param  array<int, string>  $dates  ISO dates, in any order
     */
    public static function isContiguous(array $dates): bool
    {
        $dates = self::normalise($dates);

        if (count($dates) < 2) {
            return true;
        }

        // Compared as dates rather than by counting the difference: an unbroken run of n
        // days ends exactly n-1 days after it starts.
        $expectedLast = $dates[0]->addDays(count($dates) - 1);

        return $expectedLast->isSameDay($dates[count($dates) - 1]);
    }

    /**
     * Build the list, printing each month and the year no more often than it has to be.
     *
     * @param  array<int, string>  $dates  ISO dates, in any order
     * @param  int|null  $max  the most days to name before summarising the rest
     */
    private static function format(array $dates, bool $short, ?int $max): string
    {
        $dates = self::normalise($dates);

        if ($dates === []) {
            return '';
        }

        $first = $dates[0];
        $last = $dates[count($dates) - 1];

        if (self::isContiguous(array_map(fn (CarbonInterface $date) => $date->toDateString(), $dates))) {
            return $short ? DateRange::shortLabel($first, $last) : DateRange::label($first, $last);
        }

        $remaining = 0;

        if ($max !== null && count($dates) > $max) {
            $remaining = count($dates) - $max;
            $dates = array_slice($dates, 0, $max);
            $last = $dates[count($dates) - 1];
        }

        $partial = $short ? 'M j' : 'F j';
        $full = $short ? 'M j, Y' : 'F j, Y';

        // With one year across the whole list it is printed once, at the end; spanning
        // two, every segment has to carry its own.
        $sameYear = $first->isSameYear($last);

        $text = self::conjoin(self::segments(self::runs($dates), $sameYear ? $partial : $full, $sameYear));

        if ($sameYear) {
            $text .= ', '.$last->format('Y');
        }

        return $remaining === 0
            ? $text
            : $text.' '.__('+:count more', ['count' => $remaining]);
    }

    /**
     * Collapse the days into the unbroken runs they contain.
     *
     * @param  array<int, CarbonImmutable>  $dates  ascending, distinct
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private static function runs(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $runs = [];
        $start = $dates[0];
        $end = $dates[0];

        foreach (array_slice($dates, 1) as $date) {
            if ($end->addDay()->isSameDay($date)) {
                $end = $date;

                continue;
            }

            $runs[] = [$start, $end];
            $start = $end = $date;
        }

        $runs[] = [$start, $end];

        return $runs;
    }

    /**
     * Render each run, leaving off a month that the run before it already named.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $runs
     * @return array<int, string>
     */
    private static function segments(array $runs, string $format, bool $sameYear): array
    {
        $segments = [];
        $previousMonth = null;

        foreach ($runs as [$start, $end]) {
            $sameMonth = $start->isSameMonth($end) && $start->isSameYear($end);

            // "Sep 9, 15 & 20" rather than "Sep 9, Sep 15 & Sep 20".
            $startText = $sameYear && $previousMonth === $start->format('Y-m')
                ? $start->format('j')
                : $start->format($format);

            $segments[] = $start->isSameDay($end)
                ? $startText
                : $startText.($sameMonth ? '–'.$end->format('j') : ' – '.$end->format($format));

            $previousMonth = $end->format('Y-m');
        }

        return $segments;
    }

    /**
     * Join the segments the way a person would read them out.
     *
     * @param  array<int, string>  $segments
     */
    private static function conjoin(array $segments): string
    {
        if (count($segments) < 2) {
            return $segments[0] ?? '';
        }

        $last = array_pop($segments);

        return implode(', ', $segments).' & '.$last;
    }

    /**
     * The given days, sorted, distinct, and as dates rather than strings.
     *
     * @param  array<int, string>  $dates
     * @return array<int, CarbonImmutable>
     */
    private static function normalise(array $dates): array
    {
        return collect($dates)
            ->map(fn (string $date) => CarbonImmutable::parse($date)->startOfDay())
            ->unique(fn (CarbonImmutable $date) => $date->toDateString())
            ->sort()
            ->values()
            ->all();
    }
}
