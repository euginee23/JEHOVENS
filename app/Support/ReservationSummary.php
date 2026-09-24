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
    /**
     * @param  array<int, string>  $dates  the days this reservation covers, as ISO strings
     */
    public function __construct(
        public string $type,
        public string $reference,
        public string $guestName,
        public string $guestEmail,
        public string $detail,
        public CarbonInterface $occursAt,
        public string $occursAtLabel,
        public array $dates,
        public int $days,
        public int $total,
        public int $paid,
        public int $balance,
        public BookingStatus $status,
        public CarbonInterface $placedAt,
    ) {}

    /**
     * Whether the days covered have gaps in them, which a guest reading a range of dates
     * would otherwise take to mean everything in between is theirs.
     */
    public function hasGaps(): bool
    {
        return count($this->dates) > 1 && ! DateList::isContiguous($this->dates);
    }

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
        $dates = $booking->dateList();

        return new self(
            type: __('Function hall'),
            reference: $booking->reference,
            guestName: $booking->guest_name,
            guestEmail: $booking->guest_email,
            detail: $booking->hall->name,
            occursAt: $startsAt,
            occursAtLabel: DateList::shortLabel($dates).' · '
                .($booking->days > 1 ? __(':hours each day', ['hours' => $hours]) : $hours),
            dates: $dates,
            days: $booking->days,
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
        $dates = $booking->dateList();
        $hours = trans_choice('{1} :count hour|[2,*] :count hours', $booking->hours, ['count' => $booking->hours]);

        return new self(
            type: __('Room'),
            reference: $booking->reference,
            guestName: $booking->guest_name,
            guestEmail: $booking->guest_email,
            detail: $booking->room->name,
            occursAt: $booking->starts_at,
            // An overnight stay is written check-in to check-out, which is the span the
            // guest recognises — the room is theirs for the nights between the two.
            occursAtLabel: match (true) {
                $booking->isOvernight() => DateRange::shortLabel($booking->starts_at, $booking->ends_at).' · '.$booking->stayLabel(),
                $booking->days > 1 => DateList::shortLabel($dates).' · '.$booking->starts_at->format('g:i A').' · '
                    .__(':hours each day', ['hours' => $hours]),
                default => $booking->starts_at->format('M j, Y · g:i A').' · '.$hours,
            },
            dates: $dates,
            days: $booking->days,
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
        $dates = $order->dateList();

        return new self(
            type: __('Catering'),
            reference: $order->reference,
            guestName: $order->guest_name,
            guestEmail: $order->guest_email,
            detail: $order->package->name,
            occursAt: $order->start_date,
            occursAtLabel: DateList::shortLabel($dates).' · '
                .($order->days > 1 ? __(':guests each day', ['guests' => $guests]) : $guests),
            dates: $dates,
            days: $order->days,
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
