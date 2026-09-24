<?php

namespace App\Models\Contracts;

use Illuminate\Support\Carbon;

/**
 * A reservation that covers a set of chosen days — a hall booking, a room booking, or a
 * catering order.
 *
 * The days are reached through this contract rather than through the relation for the
 * same reason photos are: Eloquent's HasMany is invariant in its model type, so an
 * interface cannot usefully hand back `HasMany<BookingDate>` as `HasMany<ReservationDate>`.
 * Callers that only want to know *which days* — the calendar, the summaries, the emails —
 * want the ISO strings anyway.
 */
interface Schedulable
{
    /**
     * The days this reservation covers, as sorted ISO strings.
     *
     * @return array<int, string>
     */
    public function dateList(): array;

    /**
     * Replace the days this reservation covers, and bring its span in step.
     *
     * @param  array<int, string>  $dates  ISO dates, in any order
     */
    public function syncDates(array $dates): void;

    /**
     * Whether the chosen days form an unbroken run.
     */
    public function isContiguous(): bool;

    /**
     * The last day this reservation covers, or null when it covers none.
     */
    public function lastDate(): ?Carbon;

    /**
     * Whether this reservation's last day has arrived.
     */
    public function hasFinished(): bool;
}
