<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Which dates a venue can still be booked for, over a window of days.
 *
 * A date is only *unavailable* when nothing more can be sold on it — for a hall, when
 * existing bookings cover the whole opening window; for a room, when it is occupied for
 * every hour a guest could check in. A date with a free morning is *partial*: still
 * pickable, but worth flagging so the guest is not surprised when a time is rejected.
 *
 * This drives the calendar picker on the booking pages. It is a convenience, not the
 * guard — the pages still assert availability again on submit, under a row lock.
 */
readonly class Availability
{
    /**
     * @param  array<int, string>  $unavailable  ISO dates nothing more can be sold on
     * @param  array<int, string>  $partial  ISO dates that are spoken for in part
     * @param  array<string, array<int, string>>  $busyLabels  ISO date => the hours already taken
     */
    public function __construct(
        public array $unavailable,
        public array $partial,
        public array $busyLabels,
    ) {}

    /**
     * Nothing is blocked — the answer for a venue with no capacity rule, and the safe
     * default before a guest has picked one.
     */
    public static function none(): self
    {
        return new self([], [], []);
    }

    /**
     * Which dates the given hall is spoken for between two dates.
     */
    public static function forHall(int $hallId, CarbonInterface $from, CarbonInterface $until): self
    {
        $occupied = [];

        $bookings = Booking::query()
            ->blocking()
            ->where('hall_id', $hallId)
            ->whereDate('start_date', '<=', $until)
            ->whereDate('end_date', '>=', $from)
            ->get(['start_date', 'end_date', 'start_hour', 'end_hour']);

        foreach ($bookings as $booking) {
            // The same hours are held on every day the booking runs for.
            foreach (self::datesBetween($booking->start_date, $booking->end_date) as $date) {
                $occupied[$date][] = [$booking->start_hour, $booking->end_hour];
            }
        }

        return self::classify($occupied, Hall::OPENS_AT, Hall::CLOSES_AT, $from, $until);
    }

    /**
     * Which dates the given room is spoken for between two dates.
     */
    public static function forRoom(int $roomId, CarbonInterface $from, CarbonInterface $until): self
    {
        $occupied = [];

        $bookings = RoomBooking::query()
            ->blocking()
            ->where('room_id', $roomId)
            ->whereDate('starts_at', '<=', $until)
            ->whereDate('ends_at', '>=', $from)
            ->get(['starts_at', 'ends_at']);

        foreach ($bookings as $booking) {
            // A stay is one continuous span, so clip it to each day it touches.
            foreach (self::datesBetween($booking->starts_at, $booking->ends_at) as $date) {
                $dayStart = Carbon::parse($date)->startOfDay();
                $dayEnd = $dayStart->copy()->addDay();

                $overlapStart = $booking->starts_at->greaterThan($dayStart) ? $booking->starts_at : $dayStart;
                $overlapEnd = $booking->ends_at->lessThan($dayEnd) ? $booking->ends_at : $dayEnd;

                // A stay ending at midnight touches the next date without occupying it.
                if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
                    continue;
                }

                $occupied[$date][] = [
                    (int) floor($dayStart->diffInMinutes($overlapStart) / 60),
                    (int) ceil($dayStart->diffInMinutes($overlapEnd) / 60),
                ];
            }
        }

        // Guests may check in as late as ENTRY_CLOSES_AT and stay past midnight, so the
        // window that has to be free runs to the end of that hour.
        return self::classify($occupied, Room::ENTRY_OPENS_AT, Room::ENTRY_CLOSES_AT + 1, $from, $until);
    }

    /**
     * Catering has no per-day capacity rule, so no date is ever closed to a new order.
     *
     * This exists so the booking pages can ask every venue type the same question. Give
     * catering a capacity rule and this is the one place that needs to learn about it.
     */
    public static function forCateringPackage(int $packageId, CarbonInterface $from, CarbonInterface $until): self
    {
        return self::none();
    }

    /**
     * Whether nothing more can be sold on the given date.
     */
    public function isUnavailable(string $date): bool
    {
        return in_array($date, $this->unavailable, strict: true);
    }

    /**
     * Whether the given date is spoken for in part but still bookable.
     */
    public function isPartial(string $date): bool
    {
        return in_array($date, $this->partial, strict: true);
    }

    /**
     * The hours already taken on the given date, for showing beside a partial day.
     *
     * @return array<int, string>
     */
    public function busyHours(string $date): array
    {
        return $this->busyLabels[$date] ?? [];
    }

    /**
     * Whether every date from `$start` to `$end` inclusive can still be booked.
     *
     * A guest picking the far end of a range must not be able to book straight through a
     * day that is already full, so the calendar closes off any end date that would.
     */
    public function rangeIsClear(string $start, string $end): bool
    {
        foreach (self::datesBetween(Carbon::parse($start), Carbon::parse($end)) as $date) {
            if ($this->isUnavailable($date)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sort each day's taken hours into "nothing left" and "some left".
     *
     * `$opensAt` and `$endsAt` bound the window in which something can still be sold, as
     * a half-open range: a day is full only once one unbroken block covers all of it.
     *
     * @param  array<string, array<int, array{int, int}>>  $occupied
     */
    private static function classify(array $occupied, int $opensAt, int $endsAt, CarbonInterface $from, CarbonInterface $until): self
    {
        $unavailable = [];
        $partial = [];
        $busyLabels = [];

        foreach (self::datesBetween($from, $until) as $date) {
            $intervals = self::merge($occupied[$date] ?? []);

            if ($intervals === []) {
                continue;
            }

            $busyLabels[$date] = array_map(
                fn (array $interval) => self::hour($interval[0]).'–'.self::hour($interval[1]),
                $intervals,
            );

            // One merged block spanning the whole bookable window means the day is full.
            $full = count($intervals) === 1
                && $intervals[0][0] <= $opensAt
                && $intervals[0][1] >= $endsAt;

            $full ? $unavailable[] = $date : $partial[] = $date;
        }

        return new self($unavailable, $partial, $busyLabels);
    }

    /**
     * Collapse overlapping and touching hour ranges into as few as possible.
     *
     * @param  array<int, array{int, int}>  $intervals
     * @return array<int, array{int, int}>
     */
    private static function merge(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $merged = [array_shift($intervals)];

        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;

            if ($start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
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
    private static function datesBetween(CarbonInterface $from, CarbonInterface $until): array
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
     * Render an hour on the 24-hour clock as a 12-hour label.
     */
    private static function hour(int $hour): string
    {
        if ($hour >= 24) {
            return __('midnight');
        }

        return sprintf('%d%s', $hour % 12 ?: 12, $hour >= 12 ? 'PM' : 'AM');
    }
}
