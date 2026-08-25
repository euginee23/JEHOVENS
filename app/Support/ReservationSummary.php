<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\RoomBooking;
use Carbon\CarbonInterface;

/**
 * A single reservation flattened into one shape.
 *
 * The three reservation tables disagree on field names — hall bookings and catering orders
 * call the amount received `downpayment`, room bookings call it `amount_paid`; and each
 * stores "when it happens" differently. This normalises them so the admin can list all
 * three together without juggling nulls in a Blade template.
 */
readonly class ReservationSummary
{
    public function __construct(
        public string $type,
        public string $reference,
        public string $guestName,
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
     * Build a summary from a function hall booking.
     */
    public static function fromHallBooking(Booking $booking): self
    {
        $startsAt = $booking->booking_date->copy()->setTime($booking->start_hour, 0);

        return new self(
            type: __('Function hall'),
            reference: $booking->reference,
            guestName: $booking->guest_name,
            detail: $booking->hall->name,
            occursAt: $startsAt,
            occursAtLabel: $startsAt->format('M j, Y').' · '.self::hour($booking->start_hour).'–'.self::hour($booking->end_hour),
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
            detail: $booking->room->name,
            occursAt: $booking->starts_at,
            occursAtLabel: $booking->starts_at->format('M j, Y · g:i A').' · '.trans_choice('{1} :count hour|[2,*] :count hours', $booking->hours, ['count' => $booking->hours]),
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
        return new self(
            type: __('Catering'),
            reference: $order->reference,
            guestName: $order->guest_name,
            detail: $order->package->name,
            occursAt: $order->event_date,
            occursAtLabel: $order->event_date->format('M j, Y').' · '.trans_choice('{1} :count guest|[2,*] :count guests', $order->guests, ['count' => number_format($order->guests)]),
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
