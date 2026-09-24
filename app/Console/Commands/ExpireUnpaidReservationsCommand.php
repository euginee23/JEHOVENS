<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\Contracts\Reservation;
use App\Models\RoomBooking;
use App\Notifications\ReservationHoldExpired;
use Illuminate\Console\Command;

/**
 * Put the dates of abandoned checkouts back on sale.
 *
 * A reservation is written before the guest is sent to PayMongo, which is what holds its
 * dates while they pay. Most guests pay; the rest close the tab. Without this those dates
 * would be held for ever by a booking nobody paid for, so the scheduler running this is
 * not optional — see the README.
 */
class ExpireUnpaidReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resort:expire-unpaid-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release the dates of bookings whose payment was never completed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $released = 0;

        foreach ([Booking::class, RoomBooking::class, CateringOrder::class] as $model) {
            $model::query()
                ->where('payment_status', PaymentStatus::Awaiting)
                ->whereNotNull('payment_expires_at')
                ->where('payment_expires_at', '<', now())
                ->where('status', BookingStatus::Pending)
                ->each(function (Reservation $reservation) use (&$released) {
                    $this->release($reservation);
                    $released++;
                });
        }

        $this->info($released === 0
            ? 'No unpaid bookings to release.'
            : "Released {$released} unpaid booking(s).");

        return self::SUCCESS;
    }

    /**
     * Cancel one reservation and tell the guest their dates have gone.
     *
     * Cancelled rather than deleted, so the resort can still see that someone tried and
     * gave up — that is worth knowing when the same dates keep being abandoned.
     */
    protected function release(Reservation $reservation): void
    {
        $reservation->recordPayment(PaymentStatus::Expired);

        // Cancelling is what frees the dates: Cancelled is not a blocking status.
        $reservation->transitionTo(BookingStatus::Cancelled, notify: false);

        $reservation->notifyGuestOf(new ReservationHoldExpired($reservation->toSummary()));

        $this->line("  Released {$reservation->reference()}.");
    }
}
