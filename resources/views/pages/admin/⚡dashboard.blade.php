<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\RoomBooking;
use App\Support\ReservationSummary;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::admin')]
#[Title('Dashboard')]
class extends Component {
    /**
     * How many of the newest reservations the table shows.
     */
    public const RECENT_LIMIT = 10;

    /**
     * Reservations still waiting on us to verify a payment.
     */
    #[Computed]
    public function awaitingPayment(): int
    {
        return Booking::where('status', BookingStatus::Pending)->count()
            + RoomBooking::where('status', BookingStatus::Pending)->count()
            + CateringOrder::where('status', BookingStatus::Pending)->count();
    }

    /**
     * Reservations placed since the start of this week.
     */
    #[Computed]
    public function bookedThisWeek(): int
    {
        $since = now()->startOfWeek();

        return Booking::where('created_at', '>=', $since)->count()
            + RoomBooking::where('created_at', '>=', $since)->count()
            + CateringOrder::where('created_at', '>=', $since)->count();
    }

    /**
     * Money actually received this month.
     *
     * Confirmed only — a pending reservation is one we have not verified payment for, so
     * counting it here would overstate takings.
     */
    #[Computed]
    public function confirmedRevenue(): int
    {
        $since = now()->startOfMonth();

        return (int) Booking::where('status', BookingStatus::Confirmed)->where('created_at', '>=', $since)->sum('downpayment')
            + (int) RoomBooking::where('status', BookingStatus::Confirmed)->where('created_at', '>=', $since)->sum('amount_paid')
            + (int) CateringOrder::where('status', BookingStatus::Confirmed)->where('created_at', '>=', $since)->sum('downpayment');
    }

    /**
     * Reservations happening in the next seven days that still hold their slot.
     */
    #[Computed]
    public function upcoming(): int
    {
        $from = now()->startOfDay();
        $until = now()->addWeek()->endOfDay();

        return Booking::query()->blocking()->whereBetween('booking_date', [$from, $until])->count()
            + RoomBooking::query()->blocking()->whereBetween('starts_at', [$from, $until])->count()
            + CateringOrder::query()->active()->whereBetween('event_date', [$from, $until])->count();
    }

    /**
     * The newest reservations across all three types, most recent first.
     *
     * @return Collection<int, ReservationSummary>
     */
    #[Computed]
    public function recent(): Collection
    {
        $limit = self::RECENT_LIMIT;

        $halls = Booking::with('hall')->latest()->limit($limit)->get()
            ->map(fn (Booking $booking) => ReservationSummary::fromHallBooking($booking));

        $rooms = RoomBooking::with('room')->latest()->limit($limit)->get()
            ->map(fn (RoomBooking $booking) => ReservationSummary::fromRoomBooking($booking));

        $catering = CateringOrder::with('package')->latest()->limit($limit)->get()
            ->map(fn (CateringOrder $order) => ReservationSummary::fromCateringOrder($order));

        return $halls->concat($rooms)->concat($catering)
            ->sortByDesc(fn (ReservationSummary $reservation) => $reservation->placedAt)
            ->take($limit)
            ->values();
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">{{ __('Dashboard') }}</h1>
            <p class="mt-2 text-zinc-600">
                {{ __('Reservations across function halls, rooms, and catering.') }}
            </p>
        </div>

        <p class="text-sm text-zinc-500">{{ now()->format('l, F j, Y') }}</p>
    </div>

    {{-- Stat tiles --}}
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $tiles = [
                ['label' => __('Awaiting payment'), 'value' => number_format($this->awaitingPayment), 'note' => __('Not yet verified'), 'tone' => 'amber'],
                ['label' => __('Booked this week'), 'value' => number_format($this->bookedThisWeek), 'note' => __('Since Monday'), 'tone' => 'brand'],
                ['label' => __('Confirmed revenue'), 'value' => '₱'.number_format($this->confirmedRevenue), 'note' => __('Received this month'), 'tone' => 'brand'],
                ['label' => __('Upcoming'), 'value' => number_format($this->upcoming), 'note' => __('Next 7 days'), 'tone' => 'zinc'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div wire:key="tile-{{ $loop->index }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-zinc-500">{{ $tile['label'] }}</p>

                <p @class([
                    'mt-2 text-3xl font-bold tracking-tight',
                    'text-amber-600' => $tile['tone'] === 'amber' && $tile['value'] !== '0',
                    'text-brand-700' => $tile['tone'] === 'brand',
                    'text-zinc-900' => $tile['tone'] === 'zinc' || ($tile['tone'] === 'amber' && $tile['value'] === '0'),
                ])>
                    {{ $tile['value'] }}
                </p>

                <p class="mt-1 text-xs text-zinc-500">{{ $tile['note'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Recent reservations --}}
    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-zinc-900">{{ __('Recent reservations') }}</h2>
            <p class="mt-1 text-sm text-zinc-600">{{ __('The :count newest bookings and orders.', ['count' => self::RECENT_LIMIT]) }}</p>
        </div>

        @if ($this->recent->isEmpty())
            <p class="m-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                {{ __('No reservations yet. They will appear here as guests book.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-3xl text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Reference') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Type') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Guest') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('When') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Total') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Paid') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Status') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->recent as $reservation)
                            <tr wire:key="reservation-{{ $reservation->reference }}" class="transition-colors hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-zinc-900">{{ $reservation->reference }}</td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">
                                        {{ $reservation->type }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-zinc-700">{{ $reservation->guestName }}</td>

                                <td class="whitespace-nowrap px-6 py-4 text-zinc-600">
                                    <span class="block text-zinc-900">{{ $reservation->detail }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $reservation->occursAtLabel }}</span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right font-medium text-zinc-900">₱{{ number_format($reservation->total) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-600">₱{{ number_format($reservation->paid) }}</td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-amber-50 text-amber-700' => $reservation->status === BookingStatus::Pending,
                                        'bg-brand-50 text-brand-700' => $reservation->status === BookingStatus::Confirmed,
                                        'bg-zinc-100 text-zinc-500' => $reservation->status === BookingStatus::Cancelled,
                                    ])>
                                        {{ $reservation->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
