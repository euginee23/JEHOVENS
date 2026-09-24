<?php

use App\Support\DateList;
use App\Support\DateRange;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Unbroken runs
|--------------------------------------------------------------------------
|
| Most bookings still cover consecutive days, and those have to read exactly as they did
| before guests could pick days one at a time — the admin tables and the emails assert on
| this wording.
|
*/

test('a single day reads as one date', function () {
    expect(DateList::label(['2026-09-10']))->toBe('September 10, 2026')
        ->and(DateList::shortLabel(['2026-09-10']))->toBe('Sep 10, 2026');
});

test('an unbroken run is written exactly as a date range', function (array $dates) {
    $first = Carbon::parse($dates[0]);
    $last = Carbon::parse($dates[count($dates) - 1]);

    expect(DateList::label($dates))->toBe(DateRange::label($first, $last))
        ->and(DateList::shortLabel($dates))->toBe(DateRange::shortLabel($first, $last));
})->with([
    'within a month' => [['2026-09-10', '2026-09-11', '2026-09-12']],
    'across months' => [['2026-09-29', '2026-09-30', '2026-10-01']],
    'across years' => [['2026-12-31', '2027-01-01']],
]);

test('days handed over unsorted are still read in order', function () {
    expect(DateList::label(['2026-09-12', '2026-09-10', '2026-09-11']))
        ->toBe('September 10–12, 2026');
});

test('the same day twice counts once', function () {
    expect(DateList::label(['2026-09-10', '2026-09-10']))->toBe('September 10, 2026');
});

test('no days at all reads as nothing', function () {
    expect(DateList::label([]))->toBe('')
        ->and(DateList::shortLabel([]))->toBe('');
});

/*
|--------------------------------------------------------------------------
| Days with gaps
|--------------------------------------------------------------------------
*/

test('separate days in one month name the month and the year once', function () {
    expect(DateList::shortLabel(['2026-09-09', '2026-09-15', '2026-09-20']))
        ->toBe('Sep 9, 15 & 20, 2026')
        ->and(DateList::label(['2026-09-09', '2026-09-15', '2026-09-20']))
        ->toBe('September 9, 15 & 20, 2026');
});

test('two separate days are joined rather than listed', function () {
    expect(DateList::shortLabel(['2026-09-09', '2026-09-20']))->toBe('Sep 9 & 20, 2026');
});

test('a run alongside a single day collapses the run', function () {
    expect(DateList::shortLabel(['2026-09-09', '2026-09-10', '2026-09-20']))
        ->toBe('Sep 9–10 & 20, 2026');
});

test('days in different months each name their month', function () {
    expect(DateList::shortLabel(['2026-09-28', '2026-10-02']))->toBe('Sep 28 & Oct 2, 2026');
});

// The single day needs no month of its own: the run before it already ended in October.
test('a run crossing a month names both ends', function () {
    expect(DateList::shortLabel(['2026-09-30', '2026-10-01', '2026-10-15']))
        ->toBe('Sep 30 – Oct 1 & 15, 2026');
});

test('days in different years each carry their own year', function () {
    expect(DateList::shortLabel(['2026-12-30', '2027-01-05']))
        ->toBe('Dec 30, 2026 & Jan 5, 2027');
});

/*
|--------------------------------------------------------------------------
| Truncation
|--------------------------------------------------------------------------
|
| Only the short form truncates, and only when the days have gaps: an unbroken run is
| already two dates long however many days it covers.
|
*/

test('a long list of separate days is summarised in the short form', function () {
    $saturdays = ['2026-09-05', '2026-09-12', '2026-09-19', '2026-09-26', '2026-10-03', '2026-10-10', '2026-10-17'];

    expect(DateList::shortLabel($saturdays))->toBe('Sep 5, 12, 19, 26 & Oct 3, 2026 +2 more');
});

test('the full form never truncates, however many days there are', function () {
    $saturdays = ['2026-09-05', '2026-09-12', '2026-09-19', '2026-09-26', '2026-10-03', '2026-10-10', '2026-10-17'];

    expect(DateList::label($saturdays))
        ->toBe('September 5, 12, 19, 26, October 3, 10 & 17, 2026');
});

test('a long unbroken run is left alone', function () {
    $fortnight = collect(range(0, 13))
        ->map(fn (int $day) => Carbon::parse('2026-09-01')->addDays($day)->toDateString())
        ->all();

    expect(DateList::shortLabel($fortnight))->toBe('Sep 1–14, 2026');
});

/*
|--------------------------------------------------------------------------
| Contiguity
|--------------------------------------------------------------------------
*/

test('it reports whether the days form an unbroken run', function (array $dates, bool $expected) {
    expect(DateList::isContiguous($dates))->toBe($expected);
})->with([
    'none' => [[], true],
    'one' => [['2026-09-10'], true],
    'two in a row' => [['2026-09-10', '2026-09-11'], true],
    'two with a gap' => [['2026-09-10', '2026-09-12'], false],
    'unsorted but unbroken' => [['2026-09-11', '2026-09-10'], true],
    'across a month boundary' => [['2026-09-30', '2026-10-01'], true],
    'a gap in the middle' => [['2026-09-10', '2026-09-11', '2026-09-13'], false],
]);
