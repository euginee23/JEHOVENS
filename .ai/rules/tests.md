---
paths:
  - 'tests/**'
---

# Tests

## Http::fake() stubs accumulate — the first match wins
Calling `Http::fake()` a second time adds stubs rather than replacing them, and the earliest matching stub answers. A success stub set in `beforeEach` therefore shadows any failure stub a single test sets afterwards, and the test passes for the wrong reason.

In files that need both, put only `Http::preventStrayRequests()` in `beforeEach` and let each test set its own stub. `fakePayMongo()` in tests/Pest.php sets both, so call it per test, not in `beforeEach`, whenever the same file also tests gateway failures.
