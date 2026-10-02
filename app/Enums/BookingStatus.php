<?php

namespace App\Enums;

use App\Models\Concerns\ManagesReservationLifecycle;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * The human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting payment confirmation'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * A compact label for table cells and filter chips.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting payment'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * What this status means, in a sentence staff can read without asking.
     *
     * "Pending" was the one status the resort could not place, so each now says what
     * happened to the booking and what, if anything, happens next.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => __('The guest has booked but the downpayment has not been received yet. It confirms on its own once the payment comes in, and is cancelled if the payment window runs out.'),
            self::Confirmed => __('The downpayment has been received and the dates are held for the guest.'),
            self::Completed => __('The event or stay is over and the dates are free again. This is final.'),
            self::Cancelled => __('The booking will not go ahead and the dates are free again.'),
        };
    }

    /**
     * The label for a button that moves a booking to this status.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Pending => __('Reinstate'),
            self::Confirmed => __('Confirm booking'),
            self::Completed => __('Mark completed'),
            self::Cancelled => __('Cancel booking'),
        };
    }

    /**
     * Tailwind classes for this status' pill.
     */
    public function classes(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Confirmed => 'bg-brand-50 text-brand-700',
            self::Completed => 'bg-emerald-50 text-emerald-700',
            self::Cancelled => 'bg-zinc-100 text-zinc-500',
        };
    }

    /**
     * Statuses that still hold the venue for the days booked.
     *
     * Completed is deliberately absent. The resort marks a booking completed once the
     * guests have gone, and expects those days to go back on sale — holding a finished
     * event's days against a new booking serves nobody. Nothing is freed early: a
     * reservation can only be completed once its last day has arrived, which
     * {@see ManagesReservationLifecycle::transitionTo()} enforces.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /**
     * Statuses an admin may move this booking to.
     *
     * Completed is final. It used to lead back to Confirmed, which left "Mark confirmed"
     * as the only button on a completed booking and read to staff as the status flipping
     * back and forth. A mistaken completion is undone with
     * {@see ManagesReservationLifecycle::reopen()} instead, which asks first.
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [self::Pending],
        };
    }
}
