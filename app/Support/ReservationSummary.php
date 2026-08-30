<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\RoomBooking;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A single reservation flattened into one shape.
 *
 * The three reservation tables disagree on field names — hall bookings and catering orders
 * call the amount received `downpayment`, room bookings call it `amount_paid`; and each
 * stores "when it happens" differently. This normalises them so the admin can list all
 * three together without juggling nulls in a Blade template.
 *
 * It is also what every reservation email is built from, so one mail template serves
 * halls, rooms and catering alike.
 */
readonly class ReservationSummary
{
    public function __construct(
        public string $type,
        public string $reference,
        public string $guestName,
        public string $guestEmail,
        public string $detail,
        public CarbonInterface $occursAt,
        public string $occursAtLabel,
        public int $total,
        public int $paid,
        public int $balance,
        public BookingStatus $status,
        public CarbonInterface $placedAt,
    ) {}

    /**
     * Build a summary from whichever of the three reservation types this is.
     *
     * Notifications are raised from the shared lifecycle trait, which does not know which
     * table it is on, so it asks here instead of branching at every call site.
     */
    public static function from(Model $reservation): self
    {
        return match (true) {
            $reservation instanceof Booking => self::fromHallBooking($reservation),
            $reservation instanceof RoomBooking => self::fromRoomBooking($reservation),
            $reservation instanceof CateringOrder => self::fromCateringOrder($reservation),
            default => throw new InvalidArgumentException(
                $reservation::class.' is not a reservation.'
            ),
        };
    }

    /**
     * Build a summary from a function hall booking.
     */
    public static function fromHallBooking(Booking $booking): self
    {
        $startsAt = $booking->start_date->copy()->setTime($booking->start_hour, 0);

        $hours = self::hour($booking->start_hour).'–'.self::hour($booking->end_hour);

        return new self(
            type: __('Function hall'),
            reference: $booking->reference,
            guestName: $booking->guest_name,
            guestEmail: $booking->guest_email,
            detail: $booking->hall->name,
            occursAt: $startsAt,
            occursAtLabel: DateRange::shortLabel($booking->start_date, $booking->end_date).' · '
                .($booking->days > 1 ? __(':hours each day', ['hours' => $hours]) : $hours),
            total: $booking->total,
            paid: $booking->downpayment,
            balance: $booking->balance,
            status: $booking->status,
            placedAt: $booking->created_at,
        );
    }

    /**
     * Build a summary from a room booking.
     */
    public static function fromRoomBooking(RoomBooking $booking): self
    {
        return new self(
            type: __('Room'),
            reference: $booking->reference,
            guestName: $booking->guest_name,
            guestEmail: $booking->guest_email,
            detail: $booking->room->name,
            occursAt: $booking->starts_at,
            occursAtLabel: $booking->isOvernight()
                ? DateRange::shortLabel($booking->starts_at, $booking->ends_at).' · '.$booking->stayLabel()
                : $booking->starts_at->format('M j, Y · g:i A').' · '
                    .trans_choice('{1} :count hour|[2,*] :count hours', $booking->hours, ['count' => $booking->hours]),
            total: $booking->total,
            paid: $booking->amount_paid,
            balance: $booking->balance,
            status: $booking->status,
            placedAt: $booking->created_at,
        );
    }

    /**
     * Build a summary from a catering order.
     */
    public static function fromCateringOrder(CateringOrder $order): self
    {
        $guests = trans_choice('{1} :count guest|[2,*] :count guests', $order->guests, ['count' => number_format($order->guests)]);

        return new self(
            type: __('Catering'),
            reference: $order->reference,
            guestName: $order->guest_name,
            guestEmail: $order->guest_email,
            detail: $order->package->name,
            occursAt: $order->start_date,
            occursAtLabel: DateRange::shortLabel($order->start_date, $order->end_date).' · '
                .($order->days > 1 ? __(':guests each day', ['guests' => $guests]) : $guests),
            total: $order->total,
            paid: $order->downpayment,
            balance: $order->balance,
            status: $order->status,
            placedAt: $order->created_at,
        );
    }

    /**
     * Render an hour on the 24-hour clock as a 12-hour label.
     */
    private static function hour(int $hour): string
    {
        return sprintf('%d%s', $hour % 12 ?: 12, $hour >= 12 ? 'PM' : 'AM');
    }
}
