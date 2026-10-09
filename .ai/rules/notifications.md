---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Send booking email through DeliverMail, never ->notify() directly
ReservationNotification is ShouldQueue, so on a server with no queue worker booking email waits in the jobs table and never leaves. Every reservation email now goes through App\Support\DeliverMail (via ManagesReservationLifecycle::notifyGuest() / sendPlacementNotifications()), which sends immediately with notifyNow() and reports, not throws, a failed send. Queueing is opt-in with RESORT_MAIL_QUEUED=true, and only safe where a worker or QUEUE_DRAIN_ON_SCHEDULE runs. Calling Notification::route(...)->notify() on a reservation notification queues it unconditionally, so don't do that.
