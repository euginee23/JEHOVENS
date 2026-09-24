<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Contracts\Schedulable;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

        // Read off the days themselves rather than the span between them: a booking of
        // the 9th and the 20th must leave the ten days in between open.
        $bookings = self::withDatesIn(
            Booking::query()->blocking()->where('hall_id', $hallId),
            $from,
            $until,
        )->get(['id', 'start_hour', 'end_hour']);

        foreach ($bookings as $booking) {
            // The same hours are held on every day the booking covers.
            foreach ($booking->dates as $date) {
                $occupied[$date->iso()][] = [$booking->start_hour, $booking->end_hour];
            }
        }

        return self::classify($occupied, Hall::OPENS_AT, Hall::CLOSES_AT, $from, $until);
    }

    /**
     * Which dates the given room is spoken for between two dates.
     */
    public static function forRoom(int $roomId, CarbonInterface $from, CarbonInterface $until): self
    {
        // A day earlier than asked for, so a stay checking in yesterday and running past
        // midnight is counted against this morning.
        $occupied = self::roomOccupancy($roomId, $from->copy()->subDay(), $until);

        // Guests may check in as late as ENTRY_CLOSES_AT and stay past midnight, so the
        // window that has to be free runs to the end of that hour.
        return self::classify($occupied, Room::ENTRY_OPENS_AT, Room::ENTRY_CLOSES_AT + 1, $from, $until);
    }

    /**
     * The hours the given room is already taken for, by date.
     *
     * Derived from the days each stay holds rather than from the span between check-in and
     * check-out, so a guest booking day use on the 5th and the 20th leaves the fortnight
     * between them open.
     *
     * @return array<string, array<int, array{int, int}>>
     */
    public static function roomOccupancy(int $roomId, CarbonInterface $from, CarbonInterface $until, ?int $ignoring = null): array
    {
        $occupied = [];

        $bookings = self::withDatesIn(
            RoomBooking::query()
                ->blocking()
                ->where('room_id', $roomId)
                ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring)),
            $from,
            $until,
        )->get(['id', 'starts_at', 'hours', 'nights']);

        foreach ($bookings as $booking) {
            // An overnight stay holds each of its nights around the clock; day use holds
            // the same block of hours on each day it was sold for.
            $perDay = $booking->isOvernight() ? Room::HOURS_PER_NIGHT : $booking->hours;

            foreach (self::intervalsFor($booking->dateList(), $booking->starts_at->hour, $perDay) as $date => $intervals) {
                $occupied[$date] = [...($occupied[$date] ?? []), ...$intervals];
            }
        }

        return $occupied;
    }

    /**
     * The hours a stay of `$hoursPerDay` from `$entryHour` takes up on each of `$dates`.
     *
     * A block running past midnight spills onto the following date, which is how a stay
     * checking in at 10 PM keeps the small hours of the next morning.
     *
     * @param  array<int, string>  $dates  ISO dates
     * @return array<string, array<int, array{int, int}>>
     */
    public static function intervalsFor(array $dates, int $entryHour, int $hoursPerDay): array
    {
        $intervals = [];

        foreach ($dates as $date) {
            $ends = $entryHour + $hoursPerDay;

            $intervals[$date][] = [$entryHour, min($ends, 24)];

            if ($ends > 24) {
                $spill = Carbon::parse($date)->addDay()->toDateString();

                $intervals[$spill][] = [0, $ends - 24];
            }
        }

        return $intervals;
    }

    /**
     * Which of the given days the reservations matched by `$query` already cover.
     *
     * The window is narrowed in SQL so the index on `date` does the work, and the exact
     * days are matched in PHP: a `date` column reads back as a plain date on MySQL and as
     * a midnight timestamp on SQLite, and one `IN` list cannot match both.
     *
     * @template TReservation of Model&Schedulable
     *
     * @param  Builder<TReservation>  $query
     * @param  array<int, string>  $dates  ISO dates
     * @return array<int, string>
     */
    public static function takenDates(Builder $query, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        sort($dates);

        $reservations = self::withDatesIn(
            $query,
            Carbon::parse($dates[0]),
            Carbon::parse($dates[count($dates) - 1]),
        )->get(['id']);

        $covered = $reservations
            ->flatMap(fn (Schedulable $reservation): array => $reservation->dateList())
            ->unique()
            ->all();

        return array_values(array_intersect($dates, $covered));
    }

    /**
     * Whether any of the wanted hours run into hours already taken.
     *
     * @param  array<string, array<int, array{int, int}>>  $wanted
     * @param  array<string, array<int, array{int, int}>>  $occupied
     */
    public static function clashes(array $wanted, array $occupied): bool
    {
        foreach ($wanted as $date => $intervals) {
            foreach ($intervals as [$start, $end]) {
                foreach ($occupied[$date] ?? [] as [$takenStart, $takenEnd]) {
                    // Half-open: a stay ending exactly as another begins is no clash.
                    if ($start < $takenEnd && $end > $takenStart) {
                        return true;
                    }
                }
            }
        }

        return false;
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
     * Whether any of the given dates can no longer be booked.
     *
     * Guests pick their days one at a time, so this checks the days they actually chose
     * rather than everything between the first and the last.
     *
     * @param  array<int, string>  $dates  ISO dates
     */
    public function anyUnavailable(array $dates): bool
    {
        foreach ($dates as $date) {
            if ($this->isUnavailable($date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Narrow a reservation query to those covering a day inside the window, with exactly
     * those days loaded.
     *
     * Two queries rather than one join, so each reservation still arrives as one model
     * with its own list of days.
     *
     * @template TReservation of Model&Schedulable
     *
     * @param  Builder<TReservation>  $query
     * @return Builder<TReservation>
     */
    private static function withDatesIn(Builder $query, CarbonInterface $from, CarbonInterface $until): Builder
    {
        // Half-open on the far end rather than BETWEEN: a `date` column reads back as a
        // midnight timestamp on SQLite, which sorts *after* the bare date it equals, so an
        // inclusive upper bound would drop the last day of the window.
        $start = $from->copy()->startOfDay()->toDateString();
        $end = $until->copy()->startOfDay()->addDay()->toDateString();

        $within = fn ($dates) => $dates->where('date', '>=', $start)->where('date', '<', $end);

        return $query->whereHas('dates', $within)->with(['dates' => $within]);
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

        foreach (DateRange::daysBetween($from, $until) as $date) {
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
