---
paths:
  - 'resources/views/mail/**'
---

# Mail

## Guest email templates include the resort contact
Every template a guest receives ends with @include('mail.partials.resort-contact') just before the sign-off. ReservationNotification passes `$contact` (ResortSetting::current(), set by staff in Settings → Mail) and sets Reply-To to its email. The partial shows nothing until staff fill it in. new-reservation-alert goes to the resort, so it leaves the partial out and its Reply-To is the guest instead.
