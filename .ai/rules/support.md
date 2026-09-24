---
paths:
  - app/Support/Availability.php
---

# Support

## Never match reservation `date` columns with whereIn or an inclusive BETWEEN
A `date` column reads back as a plain date on MySQL but as a midnight timestamp ('2027-04-10 00:00:00') on SQLite, which tests use. So `whereIn('date', ['2027-04-10'])` matches on the server and silently matches nothing in tests, and `whereBetween` drops the last day of the window because the timestamp sorts after the bare date.

Narrow in SQL with a half-open range (`>= $start` and `< $end->addDay()`), then match the exact days in PHP against `dateList()`. `Availability::takenDates()` and `withDatesIn()` already do this — reuse them rather than writing a new date comparison.
