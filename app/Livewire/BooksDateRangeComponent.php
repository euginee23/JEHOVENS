<?php

namespace App\Livewire;

use App\Support\Availability;
use App\Support\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Base for the guest-facing booking pages, which all reserve a run of consecutive days.
 *
 * A base class rather than a trait, for the same reason as ManagesPhotosComponent: the
 * concrete components are anonymous classes inside Blade single-file components, which
 * static analysis cannot see, so a trait would appear unused.
 *
 * Subclasses say which venue's availability to load; everything else — the calendar
 * month, picking a range, and counting the days — is handled here.
 */
abstract class BooksDateRangeComponent extends Component
{
    /**
     * The first day of the booking. Shareable, so the homepage availability bar can
     * hand a date straight to the page.
     */
    #[Url(as: 'date')]
    public string $start_date = '';

    /**
     * The last day of the booking, inclusive. Equal to the start for a single day.
     */
    public string $end_date = '';

    /**
     * The month the calendar is showing, as an ISO date. Empty means "work it out".
     */
    public string $month = '';

    /**
     * Which dates are already spoken for, over the window the calendar needs.
     */
    abstract protected function availabilityFor(CarbonInterface $from, CarbonInterface $until): Availability;

    /**
     * The month the calendar is showing — the month of the chosen start date, or this
     * month, until the guest navigates away from it.
     */
    #[Computed]
    public function calendar(): Carbon
    {
        $month = $this->month !== ''
            ? Carbon::parse($this->month)
            : Carbon::parse($this->start_date !== '' ? $this->start_date : today());

        return $month->startOfMonth();
    }

    /**
     * Which dates the chosen venue is already spoken for.
     *
     * The window runs from the chosen start date — not merely the start of the visible
     * grid — so a range spanning two months can still be checked for a sold-out day
     * the guest has scrolled past.
     */
    #[Computed]
    public function availability(): Availability
    {
        // Called as a method, not read as `$this->calendar`: Livewire resolves computed
        // properties by magic, which static analysis cannot follow. Blade still reads
        // them as properties, and gets the cached value.
        $month = $this->calendar();

        $gridStart = $month->copy()->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(CarbonInterface::MONDAY);

        if ($this->start_date !== '') {
            $gridStart = $gridStart->min(Carbon::parse($this->start_date)->startOfDay());
        }

        return $this->availabilityFor($gridStart, $gridEnd);
    }

    /**
     * How many days the booking covers, counting both ends. Zero until a range is picked.
     */
    #[Computed]
    public function days(): int
    {
        if (! $this->hasDateRange()) {
            return 0;
        }

        return (int) Carbon::parse($this->start_date)->startOfDay()
            ->diffInDays(Carbon::parse($this->end_date)->startOfDay()) + 1;
    }

    /**
     * How many nights fall inside the booking. Zero for a single day.
     */
    #[Computed]
    public function nights(): int
    {
        return max($this->days() - 1, 0);
    }

    /**
     * Whether both ends of the range are set.
     */
    public function hasDateRange(): bool
    {
        return $this->start_date !== '' && $this->end_date !== '';
    }

    /**
     * The chosen range written out, e.g. "September 10–12, 2026".
     */
    #[Computed]
    public function rangeLabel(): string
    {
        if (! $this->hasDateRange()) {
            return '';
        }

        return DateRange::label(
            Carbon::parse($this->start_date),
            Carbon::parse($this->end_date),
        );
    }

    /**
     * Take a date from the calendar.
     *
     * Clicking a date later than the current start extends the range to it; clicking the
     * start again, or any earlier date, begins a new one. A single-day booking is
     * therefore one click, and the range is never left half-picked and invalid.
     */
    public function selectDate(string $date): void
    {
        $picked = rescue(fn () => Carbon::parse($date)->startOfDay(), null, report: false);

        if (! $picked || $picked->lt(today())) {
            return;
        }

        $date = $picked->toDateString();

        if ($this->start_date !== '' && $date > $this->start_date) {
            $this->end_date = $date;
        } else {
            $this->start_date = $date;
            $this->end_date = $date;
        }

        $this->resetValidation(['start_date', 'end_date']);

        // Drop the caches before the hook runs, so subclasses read the new range.
        unset($this->days, $this->nights, $this->rangeLabel, $this->availability);

        $this->afterDateRangeChange();
    }

    /**
     * Move the calendar a month at a time, never back past the current month.
     */
    public function shiftMonth(int $delta): void
    {
        $this->month = $this->calendar()
            ->copy()
            ->addMonths($delta)
            ->max(today()->startOfMonth())
            ->toDateString();

        unset($this->calendar, $this->availability);
    }

    /**
     * Clear the range and send the calendar back to where it started.
     */
    protected function resetDateRange(): void
    {
        $this->start_date = '';
        $this->end_date = '';
        $this->month = '';

        unset($this->calendar, $this->availability, $this->days, $this->nights);
    }

    /**
     * Drop a start date from the query string that the form could never accept, so a
     * stale link opens on an empty calendar instead of one the rules will reject.
     */
    protected function discardUnusableDate(): void
    {
        if ($this->start_date === '') {
            return;
        }

        $date = rescue(fn () => Carbon::parse($this->start_date), null, report: false);

        if (! $date || $date->startOfDay()->lt(today())) {
            $this->start_date = '';
            $this->end_date = '';

            return;
        }

        // A shared link carries only the first day; treat it as a single-day booking.
        if ($this->end_date === '') {
            $this->end_date = $date->toDateString();
        }
    }

    /**
     * Hook for subclasses that need to recompute something when the range moves.
     */
    protected function afterDateRangeChange(): void
    {
        //
    }
}
