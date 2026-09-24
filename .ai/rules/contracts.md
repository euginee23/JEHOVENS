---
paths:
  - 'app/Models/Contracts/**'
---

# Contracts

## Reservation contracts declare methods, not @property tags
Larastan resolves model attributes from the concrete class, so `@property` tags on an interface are ignored — `$reservation->payment_status` fails phpstan even typed as `Model&Reservation`. Accessors on the contract (`paymentStatus()`, `reference()`, `balanceRemaining()`, `recordPayment()`) are what make cross-type payment code check out; their bodies live in `ManagesReservationLifecycle` so each model gets them once.

Also: resolving a model from a `class-string` variable loses the intersection. `PayMongoPayments::query()` matches over the three concrete models instead, which keeps the type.
