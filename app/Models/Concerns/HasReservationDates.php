<?php

namespace App\Models\Concerns;

use App\Models\ReservationDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The days a reservation covers, shared by hall bookings, room bookings and catering
 * orders.
 *
 * Each type keeps its own table — `booking_dates`, `room_booking_dates`,
 * `catering_order_dates` — so the using model names its date class, exactly as it does
 * for photos. Only the behaviour is shared.
 *
 * The chosen days are the record here. `start_date` and `end_date` on the parent are the
 * outer bounds of those days and nothing more: a booking covering only the 9th and the
 * 20th spans eleven days but was sold two, so `days` counts these rows.
 *
 * @template TDate of ReservationDate
 */
trait HasReservationDates
{
    /**
     * The most days one reservation may cover.
     *
     * A cap rather than no limit at all: the calendar loads availability for everything
     * chosen, and a runaway selection would otherwise query a year at a time.
     */
    public const MAX_DATES = 30;

    /**
     * The model holding this type's dates.
     *
     * @return class-string<TDate>
     */
    abstract public function dateModel(): string;

    /**
     * The days this reservation covers, earliest first.
     *
     * @return HasMany<TDate, $this>
     */
    public function dates(): HasMany
    {
        return $this->hasMany($this->dateModel())->orderBy('date');
    }

    /**
     * The days this reservation covers, as sorted ISO strings.
     *
     * Reads the loaded relation when there is one, so a list that has been eager-loaded
     * for a table of reservations does not go back to the database a row at a time.
     *
     * @return array<int, string>
     */
    public function dateList(): array
    {
        $dates = $this->relationLoaded('dates')
            ? $this->getRelation('dates')
            : $this->dates()->get();

        return $dates->map(fn (ReservationDate $date) => $date->iso())->sort()->values()->all();
    }

    /**
     * Replace the days this reservation covers, and bring the parent's span in step.
     *
     * Whole-sale replacement rather than a diff: the set is small, the guest picks it in
     * one go, and this way the parent's bounds can never drift from the rows.
     *
     * @param  array<int, string>  $dates  ISO dates, in any order
     */
    public function syncDates(array $dates): void
    {
        $dates = collect($dates)
            ->map(fn (string $date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            throw new InvalidArgumentException('A reservation must cover at least one date.');
        }

        $this->dates()->delete();

        $this->dates()->createMany(
            $dates->map(fn (string $date) => ['date' => $date])->all()
        );

        $this->unsetRelation('dates');

        $this->applyDateSpan($dates->all());
    }

    /**
     * Bring the parent's own date columns in step with the days just written.
     *
     * Hall bookings and catering orders store the outer bounds alongside the count; room
     * bookings bound their stay with `starts_at` and `ends_at`, which carry a time of day
     * this trait knows nothing about, so that type overrides this.
     *
     * @param  array<int, string>  $dates  ISO dates, ascending
     */
    protected function applyDateSpan(array $dates): void
    {
        // `days` counts the days sold, which is not the span when they have gaps.
        $this->forceFill([
            'start_date' => $dates[0],
            'end_date' => $dates[count($dates) - 1],
            'days' => count($dates),
        ])->save();
    }

    /**
     * Whether the chosen days form an unbroken run.
     */
    public function isContiguous(): bool
    {
        $dates = $this->dateList();

        if (count($dates) < 2) {
            return true;
        }

        // Compared as dates rather than by counting the difference: an unbroken run of n
        // days ends exactly n-1 days after it starts, and nothing here has to trust a
        // day count that daylight saving or a float return could round.
        $expectedLast = CarbonImmutable::parse($dates[0])->addDays(count($dates) - 1);

        return $expectedLast->toDateString() === $dates[count($dates) - 1];
    }

    /**
     * The last day this reservation covers, or null when it covers none.
     */
    public function lastDate(): ?Carbon
    {
        $dates = $this->dateList();

        return $dates === [] ? null : Carbon::parse(end($dates));
    }

    /**
     * Whether this reservation's last day has arrived.
     *
     * What "arrived" means rather than "passed": the resort marks a booking completed on
     * the day itself, once the guests have gone, and expects the slot to free up for
     * that same day.
     */
    public function hasFinished(): bool
    {
        $last = $this->lastDate();

        return $last !== null && $last->startOfDay()->lte(today());
    }
}
