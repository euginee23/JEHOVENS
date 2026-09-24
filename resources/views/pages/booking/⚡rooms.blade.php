<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Livewire\BooksDatesComponent;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomRate;
use App\Support\Availability;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new
#[Layout('layouts::marketing')]
#[Title('Book a Room')]
class extends BooksDatesComponent {
    public ?int $room_id = null;

    #[Url(as: 'entry')]
    public ?int $entry_hour = null;

    /**
     * Either 'day' or 'overnight'.
     *
     * Days are taken one at a time now, which day use is happy with — two Saturdays by
     * the pool is two separate blocks. A night's sleep is not like that: nights run into
     * one another, so an overnight stay has to be an unbroken run and the guest has to
     * say which of the two they mean rather than have it guessed from the gaps.
     */
    public string $stay_mode = 'day';

    /**
     * The day-use duration the guest picked. Only asked for when they are not staying
     * the night — an overnight stay is always sold at the room's 24-hour rate.
     */
    public ?int $rate_id = null;

    /**
     * The duration the guest asked for on the homepage's availability bar. Rates belong
     * to a room, so this is held as a plain hour count until a room is picked and the
     * matching rate can be resolved.
     */
    #[Url(as: 'hours')]
    public ?int $preferred_hours = null;

    /**
     * Either 'downpayment' or 'full'. Kept as a string because Flux radio values are
     * strings, and a boolean wire:model never matches one.
     */
    public string $payment_option = 'downpayment';

    /**
     * Take the contact details of a signed-in guest, whatever a guest coming back from
     * PayMongo brought with them, and whatever the homepage availability bar sent over.
     */
    public function mount(): void
    {
        $this->prefillFromSession();
        $this->discardUnusableSearch();
    }

    /**
     * How the payment routes name this page.
     */
    protected function reservationType(): string
    {
        return 'room';
    }

    /**
     * Drop query-string values the form could never accept, so a stale link or a
     * hand-edited URL opens on an empty field instead of one the rules will reject.
     *
     * The homepage availability bar pre-selects nothing, so an untouched search arrives as
     * `?date=&entry=&hours=`. Livewire hydrates those blanks to null on its own; what this
     * guards is a filled-in value the form would then refuse.
     */
    protected function discardUnusableSearch(): void
    {
        $this->discardUnusableDate();

        if ($this->entry_hour !== null && ($this->entry_hour < Room::ENTRY_OPENS_AT || $this->entry_hour > Room::ENTRY_CLOSES_AT)) {
            $this->entry_hour = null;
        }
    }

    /**
     * Which dates the chosen room is already taken for.
     */
    protected function availabilityFor(CarbonInterface $from, CarbonInterface $until): Availability
    {
        return $this->room_id
            ? Availability::forRoom($this->room_id, $from, $until)
            : Availability::none();
    }

    /**
     * Validation rules for the booking form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'stay_mode' => ['required', 'in:day,overnight'],
            'dates' => [
                'required',
                'array',
                'min:1',
                'max:'.self::MAX_DATES,
                function (string $attribute, mixed $value, callable $fail) {
                    foreach ((array) $value as $date) {
                        if (rescue(fn () => Carbon::parse($date)->startOfDay(), null, report: false)?->lt(today()) ?? true) {
                            $fail(__('Pick dates from today onwards.'));

                            return;
                        }
                    }

                    if (! $this->isOvernight()) {
                        return;
                    }

                    if ($this->room && ! $this->room->sellsOvernightStays()) {
                        $fail(__('This room is for day use only. Switch to day use, or choose another room to stay the night.'));

                        return;
                    }

                    if (! $this->datesAreContiguous()) {
                        $fail(__('An overnight stay has to be a run of nights in a row. Remove the gap, or switch to day use.'));
                    }
                },
            ],
            'entry_hour' => ['required', 'integer', 'min:'.Room::ENTRY_OPENS_AT, 'max:'.Room::ENTRY_CLOSES_AT],

            // Only day-use stays need a duration; overnight ones are sold by the night.
            'rate_id' => [
                Rule::requiredIf(fn () => ! $this->isOvernight()),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, callable $fail) {
                    if ($this->room_id && ! RoomRate::where('id', $value)->where('room_id', $this->room_id)->exists()) {
                        $fail(__('Choose one of the durations offered for this room.'));
                    }
                },
            ],
            'payment_option' => ['required', 'in:downpayment,full'],
            ...$this->guestRules(),
        ];
    }

    /**
     * Human-readable messages for the rules that need one.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'room_id.required' => __('Choose a room first.'),
            'dates.required' => __('Pick your dates on the calendar.'),
            'dates.max' => __('A booking can cover at most :count days.', ['count' => self::MAX_DATES]),
            'entry_hour.required' => __('Choose your time of entry.'),
            'rate_id.required' => __('Choose how long you are staying.'),
            'guest_phone.regex' => __('Enter an 11-digit mobile number starting with 09, e.g. 09123456789.'),
        ];
    }

    /**
     * The rooms a guest can currently book.
     *
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()->active()->with(['rates', 'photos'])->get();
    }

    /**
     * The room the guest has selected, if any.
     */
    #[Computed]
    public function room(): ?Room
    {
        return $this->room_id ? $this->rooms->firstWhere('id', $this->room_id) : null;
    }

    /**
     * Whether the guest is staying the night rather than booking the room for the day.
     *
     * Taken from what the guest chose rather than inferred from how many days they
     * picked: two separate days is day use twice over, not one night.
     */
    public function isOvernight(): bool
    {
        return $this->stay_mode === 'overnight';
    }

    /**
     * How many nights the stay covers.
     *
     * In overnight mode the days chosen *are* the nights slept — pick the 10th, 11th and
     * 12th and you have three nights, leaving on the 13th. Day use has none.
     */
    #[Computed]
    public function nights(): int
    {
        return $this->isOvernight() ? $this->days : 0;
    }

    /**
     * Clear a day-use duration when the guest switches to staying the night, so a stale
     * rate never lingers behind the hidden selector.
     */
    public function updatedStayMode(): void
    {
        if ($this->isOvernight()) {
            $this->rate_id = null;
        }

        $this->resetValidation(['dates', 'rate_id']);

        unset($this->nights, $this->rate, $this->quote, $this->stayHours);
    }

    /**
     * The rate this stay is priced from.
     *
     * A day-use booking uses whichever duration the guest picked. An overnight stay is
     * always sold at the room's 24-hour rate, charged once per night, so the duration
     * selector is not shown and the rate is resolved here instead.
     */
    #[Computed]
    public function rate(): ?RoomRate
    {
        if (! $this->room) {
            return null;
        }

        return $this->isOvernight()
            ? $this->room->overnightRate()
            : $this->room->rates->firstWhere('id', $this->rate_id);
    }

    /**
     * The live price breakdown, once a room and a length of stay are chosen.
     *
     * @return array{total: int, amount_paid: int, balance: int}|null
     */
    #[Computed]
    public function quote(): ?array
    {
        return $this->room && $this->rate && $this->hasDates()
            ? $this->room->quote($this->rate, $this->payingInFull(), $this->nights, $this->days)
            : null;
    }

    /**
     * How long the stay runs for in total, in hours.
     */
    #[Computed]
    public function stayHours(): ?int
    {
        if (! $this->rate) {
            return null;
        }

        return $this->isOvernight()
            ? $this->nights * Room::HOURS_PER_NIGHT
            : $this->rate->hours;
    }

    /**
     * Whether the guest chose to settle the whole amount up front.
     */
    public function payingInFull(): bool
    {
        return $this->payment_option === 'full';
    }

    /**
     * Selectable entry times.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function entryHours(): array
    {
        $options = [];

        for ($hour = Room::ENTRY_OPENS_AT; $hour <= Room::ENTRY_CLOSES_AT; $hour++) {
            $options[$hour] = $this->formatHour($hour);
        }

        return $options;
    }

    /**
     * When the guest should arrive, once the date and entry time are known.
     */
    #[Computed]
    public function arriveBy(): ?Carbon
    {
        return $this->startsAt()?->copy()->subMinutes(Room::ARRIVE_EARLY_MINUTES);
    }

    /**
     * The booking created by this session, once payment has been acknowledged.
     */
    #[Computed]
    public function booking(): ?RoomBooking
    {
        return $this->reference ? RoomBooking::with('room')->where('reference', $this->reference)->first() : null;
    }

    /**
     * Drop a duration that belongs to a different room, then re-apply the duration the
     * guest asked for on the homepage if this room sells one that matches.
     */
    public function selectRoom(int $roomId): void
    {
        $this->room_id = $roomId;

        // Resolved off `rooms` rather than the `room` computed, which may already have
        // been cached against the previous selection earlier in this request.
        $this->rate_id = $this->preferred_hours
            ? $this->rooms->firstWhere('id', $roomId)?->rates->firstWhere('hours', $this->preferred_hours)?->id
            : null;

        $this->resetValidation(['room_id', 'dates', 'rate_id']);

        // Availability is per room, so the calendar has to be rebuilt for the new one.
        unset($this->availability, $this->rate, $this->quote, $this->stayHours);
    }

    /**
     * Drop a day-use duration once the guest starts staying the night, so a stale rate
     * never lingers behind the hidden selector.
     */
    protected function afterDatesChange(): void
    {
        unset($this->rate, $this->quote, $this->stayHours);
    }

    /**
     * Write the booking and send the guest to PayMongo to pay for it.
     *
     * The booking is written first, and that is what holds its dates while the guest is
     * on PayMongo's page — Pending already blocks. It stays unconfirmed until PayMongo
     * says the money arrived, and is released again if it never does.
     */
    public function proceedToPayment(): mixed
    {
        if (! $this->paymentsAvailable) {
            throw ValidationException::withMessages([
                'dates' => __('Online payment is temporarily unavailable. Please call the resort to book.'),
            ]);
        }

        $this->validate();
        $this->assertRoomIsAvailable();

        $quote = $this->room->quote($this->rate, $this->payingInFull(), $this->nights, $this->days);

        // Paying in full is the guest's own way out of a downpayment that is too small,
        // so point at it rather than sending them away — but only when it would help.
        $this->assertAmountIsPayable(
            $quote['amount_paid'],
            ! $this->payingInFull() && $quote['total'] >= \App\Support\PayMongo::MINIMUM_PESOS
                ? __('Choose "Pay in full" to book this.')
                : null,
        );

        // Two guests can reach this point for the same room at once, so the last check
        // runs inside the transaction that writes the booking, behind the room's row lock.
        $booking = DB::transaction(function () use ($quote) {
            $this->assertRoomIsAvailable(lock: true);

            $booking = RoomBooking::create([
                'reference' => RoomBooking::generateReference(),
                'room_id' => $this->room_id,
                'user_id' => Auth::id(),
                'guest_name' => $this->guest_name,
                'guest_phone' => $this->guest_phone,
                'guest_email' => $this->guest_email,
                'starts_at' => $this->startsAt(),
                'ends_at' => $this->endsAt(),
                'hours' => $this->hoursPerDay(),
                'nights' => $this->nights,
                'days' => $this->days,
                'pay_in_full' => $this->payingInFull(),
                ...$quote,
                'status' => BookingStatus::Pending,
                'payment_provider' => 'paymongo',
                'payment_status' => PaymentStatus::Awaiting,
                'payment_expires_at' => now()->addMinutes((int) config('services.paymongo.hold_minutes')),
            ]);

            $booking->syncDates($this->bookedDates());

            return $booking;
        });

        return $this->sendToCheckout($booking, $quote['amount_paid'], $this->room->name);
    }

    /**
     * Start over on a fresh booking form.
     */
    public function bookAnother(): void
    {
        $this->reset(['room_id', 'entry_hour', 'rate_id', 'preferred_hours', 'payment_option', 'stay_mode', 'reference', 'paymentError']);
        $this->resetDates();
        $this->mount();
    }

    /**
     * The start of the requested stay, or null while the date or time is missing.
     */
    public function startsAt(): ?Carbon
    {
        $first = $this->firstDate();

        if ($first === null || $this->entry_hour === null) {
            return null;
        }

        return Carbon::parse($first)->setTime($this->entry_hour, 0);
    }

    /**
     * When the guest checks out.
     *
     * An overnight guest leaves at their entry time the morning after their last night,
     * so three nights from the 10th entering at 2PM runs to 2PM on the 13th. A day-use
     * guest leaves once the block is up on the last day they booked.
     */
    public function endsAt(): ?Carbon
    {
        $startsAt = $this->startsAt();

        if (! $startsAt || ! $this->rate || ! $this->hasDates()) {
            return null;
        }

        return $this->isOvernight()
            ? $startsAt->copy()->addDays($this->nights)
            : Carbon::parse($this->lastDate())->setTime($this->entry_hour, 0)->addHours($this->rate->hours);
    }

    /**
     * Reject the stay if a pending or confirmed booking already overlaps it.
     *
     * The comparison is half-open on both ends, so one guest checking out at the same
     * hour another checks in is not a clash.
     */
    protected function assertRoomIsAvailable(bool $lock = false): void
    {
        $dates = $this->bookedDates();

        if ($dates === []) {
            return;
        }

        if ($lock) {
            // Everyone booking this room queues behind its row, so the check below and the
            // insert that follows cannot interleave with another guest's. Locking the
            // booking query instead would be a bet on gap locks in an empty result.
            Room::query()->whereKey($this->room_id)->lockForUpdate()->first();
        }

        $first = Carbon::parse($dates[0]);
        $last = Carbon::parse($dates[count($dates) - 1]);

        $wanted = Availability::intervalsFor($dates, (int) $this->entry_hour, $this->hoursPerDay());

        // A day either side: a stay running past midnight reaches into the next morning,
        // and one that started yesterday reaches into the first of these.
        $occupied = Availability::roomOccupancy($this->room_id, $first->copy()->subDay(), $last->copy()->addDay());

        if (Availability::clashes($wanted, $occupied)) {
            unset($this->availability);

            throw ValidationException::withMessages([
                'dates' => __('That room is taken on one of your dates. Remove that date, or pick another time or room.'),
            ]);
        }
    }

    /**
     * How many hours the room is held for on each day of the stay.
     *
     * An overnight stay holds it around the clock; day use holds the block the guest
     * chose, on each day they chose.
     */
    protected function hoursPerDay(): int
    {
        return $this->isOvernight() ? Room::HOURS_PER_NIGHT : (int) $this->rate?->hours;
    }

    /**
     * Render an hour on the 24-hour clock as a 12-hour label.
     */
    public function formatHour(int $hour): string
    {
        return sprintf('%d:00 %s', $hour % 12 ?: 12, $hour >= 12 ? 'PM' : 'AM');
    }
}; ?>

<div>
    @if ($this->booking)
        {{-- Confirmation --}}
        <section class="relative isolate overflow-hidden bg-sand-50">
            <x-marketing.glow />

            <div class="relative mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                <div class="border-t-2 border-gold-400 bg-white p-8 shadow-xl shadow-brand-950/10 ring-1 ring-sand-200 sm:p-10">
                    <div class="flex size-14 items-center justify-center bg-brand-800 text-gold-300">
                        <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                        </svg>
                    </div>

                    <h1 class="mt-8 font-serif text-4xl font-medium text-brand-900">{{ __('Booking received') }}</h1>

                    <p class="mt-3 text-brand-800/70">
                        {{ __('We are verifying your payment. You will get a confirmation once it clears — usually within 24 hours.') }}
                    </p>

                    @php $booking = $this->booking; @endphp

                    <div class="mt-6 border-s-2 border-gold-400 bg-sand-100 p-5">
                        <p class="text-sm font-semibold text-brand-800">
                            {{ __('Please arrive by :time on :date', [
                                'time' => $booking->arriveBy()->format('g:i A'),
                                'date' => $booking->arriveBy()->format('F j, Y'),
                            ]) }}
                        </p>
                        <p class="mt-1 text-sm text-brand-700">
                            {{ __('That is :minutes minutes before your check-in, so we have time to get your room ready.', ['minutes' => \App\Models\Room::ARRIVE_EARLY_MINUTES]) }}
                        </p>
                    </div>

                    <dl class="mt-8 divide-y divide-sand-200 border-y border-sand-200 text-sm">
                        @php
                            $rows = [
                                __('Reference') => $booking->reference,
                                __('Room') => $booking->room->name,
                                __('Stay') => $booking->stayLabel(),
                                __('Check-in') => $booking->starts_at->format('F j, Y \a\t g:i A'),
                                __('Check-out') => $booking->ends_at->format('F j, Y \a\t g:i A'),
                                __('Length') => trans_choice('{1} :count hour|[2,*] :count hours', $booking->hours, ['count' => $booking->hours]),
                                __('Name') => $booking->guest_name,
                                __('Phone') => $booking->guest_phone,
                                __('Email') => $booking->guest_email,
                            ];
                        @endphp

                        @foreach ($rows as $label => $value)
                            <div class="flex justify-between gap-6 py-3" wire:key="row-{{ $loop->index }}">
                                <dt class="text-brand-800/60">{{ $label }}</dt>
                                <dd class="text-right font-medium text-brand-900">{{ $value }}</dd>
                            </div>
                        @endforeach

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-brand-800/60">{{ __('Total') }}</dt>
                            <dd class="text-right font-medium text-brand-900">₱{{ number_format($booking->total) }}</dd>
                        </div>

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-brand-800/60">
                                {{ $booking->pay_in_full ? __('Paid in full') : __('Downpayment sent') }}
                            </dt>
                            <dd class="text-right font-semibold text-brand-800">₱{{ number_format($booking->amount_paid) }}</dd>
                        </div>

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-brand-800/60">{{ __('Balance on arrival') }}</dt>
                            <dd class="text-right font-medium text-brand-900">₱{{ number_format($booking->balance) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="bookAnother"
                            class="eyebrow bg-brand-800 px-6 py-4 text-[11px] text-white transition hover:bg-brand-700"
                        >
                            {{ __('Book another room') }}
                        </button>

                        <a
                            href="{{ route('home') }}"
                            class="eyebrow border border-sand-200 bg-white px-6 py-4 text-[11px] text-brand-800 transition hover:border-brand-300 hover:bg-sand-50"
                        >
                            {{ __('Back to home') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @else
        {{-- Page header --}}
        <section class="relative isolate flex h-64 items-end overflow-hidden bg-brand-950 lg:h-80">
            <img
                src="{{ asset('images/rooms/room-1.jpg') }}"
                alt="{{ __('Air-conditioned room with a double bed and wicker frame') }}"
                width="1856"
                height="1870"
                fetchpriority="high"
                decoding="async"
                class="absolute inset-0 size-full object-cover opacity-60"
            />
            <div aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-brand-950 via-brand-950/50 to-brand-950/20"></div>

            <div class="relative mx-auto w-full max-w-7xl px-4 pb-10 sm:px-6 lg:px-8 lg:pb-14">
                <p class="eyebrow text-gold-300">{{ __('Rooms') }}</p>

                <h1 class="mt-4 font-serif text-4xl/tight font-medium text-balance text-white sm:text-5xl/tight">
                    {{ __('Book a room') }}
                </h1>

                <p class="mt-4 max-w-2xl text-base/7 text-pretty text-sand-100/80">
                    {{ __('Pick a room, choose your check-in time and how long you are staying, then pay half now or the whole thing up front.') }}
                </p>
            </div>
        </section>

        <section class="bg-sand-50 pb-24 pt-12 lg:pb-32">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:items-start lg:gap-8 lg:px-8">
                {{-- Room picker --}}
                <div class="min-w-0 bg-white p-6 shadow-sm shadow-brand-950/5 ring-1 ring-sand-200 sm:p-8">
                    <h2 class="font-serif text-2xl font-medium text-brand-900">{{ __('Select a room') }}</h2>

                    @if ($this->rooms->isEmpty())
                        <p class="mt-6 border border-dashed border-sand-200 p-8 text-center text-sm text-brand-800/60">
                            {{ __('No rooms are available to book right now. Please check back later.') }}
                        </p>
                    @else
                        <div class="mt-6 space-y-4" role="radiogroup" aria-label="{{ __('Rooms') }}">
                            @foreach ($this->rooms as $room)
                                <button
                                    type="button"
                                    wire:key="room-{{ $room->id }}"
                                    wire:click="selectRoom({{ $room->id }})"
                                    role="radio"
                                    aria-checked="{{ $room_id === $room->id ? 'true' : 'false' }}"
                                    @class([
                                        'w-full border p-5 text-left transition',
                                        'border-brand-600 bg-brand-50 ring-1 ring-brand-600' => $room_id === $room->id,
                                        'border-sand-200 bg-white hover:border-gold-300 hover:bg-sand-50' => $room_id !== $room->id,
                                    ])
                                >
                                    @if ($room->photos->isNotEmpty())
                                        {{-- No dots: this card is a <button>, and nested
                                             interactive elements would both be invalid HTML
                                             and steal the click that selects the room. --}}
                                        <x-marketing.photo-slideshow
                                            :photos="$room->photos->map(fn ($photo) => ['url' => $photo->url(), 'alt' => $photo->alt, 'width' => 1600, 'height' => 1200])->all()"
                                            :dots="false"
                                            class="mb-4 aspect-3/2"
                                        />
                                    @endif

                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <h3 class="font-serif text-2xl font-medium text-brand-900">{{ $room->name }}</h3>
                                            <p class="mt-1.5 text-sm/6 text-brand-800/70">{{ $room->description }}</p>
                                        </div>

                                        <span @class([
                                            'mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                                            'border-brand-600 bg-brand-600 text-white' => $room_id === $room->id,
                                            'border-sand-200' => $room_id !== $room->id,
                                        ])>
                                            @if ($room_id === $room->id)
                                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                                                </svg>
                                            @endif
                                        </span>
                                    </div>

                                    @if ($room->rates->isNotEmpty())
                                        <div class="mt-4 flex flex-wrap gap-2 border-t border-sand-200 pt-4">
                                            @foreach ($room->rates as $rate)
                                                <span wire:key="rate-badge-{{ $rate->id }}" class="bg-brand-800 px-3 py-1.5 text-xs font-semibold text-white">
                                                    {{ __(':hours h: ₱:price', ['hours' => $rate->hours, 'price' => number_format($rate->price)]) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @error('room_id')
                        <p class="mt-4 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Booking details --}}
                <div class="min-w-0 border-t-2 border-gold-400 bg-white p-6 shadow-sm shadow-brand-950/5 ring-1 ring-sand-200 sm:p-8 lg:sticky lg:top-28 lg:max-h-[calc(100dvh-8.5rem)] lg:overflow-y-auto">
                    <h2 class="font-serif text-2xl font-medium text-brand-900">{{ __('Booking details') }}</h2>

                    <x-booking.selection
                        class="mt-5"
                        :name="$this->room?->name"
                        :prompt="__('Pick a room from the list to get started.')"
                        :facts="$this->room
                            ? $this->room->rates->map(fn ($rate) => __(':hours h · ₱:price', ['hours' => $rate->hours, 'price' => number_format($rate->price)]))->all()
                            : []"
                    />
                    <form wire:submit="proceedToPayment" class="mt-6 space-y-6">
                        {{-- Asked before the calendar, because it changes what tapping a
                             day means: a night slept, or a block of hours in the day. --}}
                        @if ($this->room?->sellsOvernightStays())
                            <flux:radio.group wire:model.live="stay_mode" :label="__('How are you staying?')" variant="segmented">
                                <flux:radio value="day" :label="__('Day use')" />
                                <flux:radio value="overnight" :label="__('Overnight')" />
                            </flux:radio.group>
                        @endif

                        <div>
                            <x-booking.availability-calendar
                                :month="$this->calendar"
                                :dates="$dates"
                                :availability="$this->availability"
                                :label="$this->isOvernight() ? __('Select your nights') : __('Select your dates')"
                                :hint="match (true) {
                                    ! $this->room => __('Pick a room first to see which dates are still open.'),
                                    $this->isOvernight() => __('Tap each night you are staying. Nights have to be in a row — you check out the morning after the last one.'),
                                    default => __('Tap each day you need the room. Tap it again to remove it — the days need not be in a row.'),
                                }"
                            />

                            @if ($this->hasDates())
                                <p class="mt-2 text-sm font-medium text-brand-900">
                                    {{ $this->datesLabel }}
                                    <span class="text-brand-800/60">
                                        @if ($this->isOvernight())
                                            {{ trans_choice('{1} · :count night|[2,*] · :count nights', $this->nights, ['count' => $this->nights]) }}
                                        @elseif ($this->days > 1)
                                            {{ trans_choice('{1} · :count day|[2,*] · :count days', $this->days, ['count' => $this->days]) }}
                                        @endif
                                    </span>
                                </p>
                            @endif

                            {{-- Flagged the moment it happens rather than on submit, so the
                                 guest sees why the gap is a problem while they can still fix it. --}}
                            @if ($this->isOvernight() && $this->days > 1 && ! $this->datesAreContiguous())
                                <p class="mt-2 text-sm font-medium text-amber-700">
                                    {{ __('Nights have to be in a row. Remove the gap, or switch to day use.') }}
                                </p>
                            @endif

                            @error('dates')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- A real empty option rather than Flux's `placeholder`, which
                             renders as `<option disabled selected>`. A disabled option is
                             not a valid selection, so once Livewire re-rendered the field
                             the browser fell back to showing the first real time — making
                             the form look filled in while the value was still empty. --}}
                        <flux:select wire:model.live="entry_hour" :label="__('Time of entry')">
                            <flux:select.option value="">{{ __('Please select a time') }}</flux:select.option>

                            @foreach ($this->entryHours as $hour => $label)
                                <flux:select.option :value="$hour">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        {{-- Day use is sold by the hour block; a stay over one or more nights
                             is always sold at the room's nightly rate, so the duration
                             selector only makes sense for the former. The block is the
                             same on each day chosen. --}}
                        @if (! $this->isOvernight())
                            <flux:select
                                wire:model.live="rate_id"
                                :label="$this->days > 1 ? __('How long each day') : __('How long for')"
                                :disabled="! $this->room"
                            >
                                <flux:select.option value="">
                                    {{ $this->room ? __('Please select a duration') : __('Pick a room first') }}
                                </flux:select.option>

                                @if ($this->room)
                                    @foreach ($this->room->rates as $rate)
                                        <flux:select.option :value="$rate->id">
                                            {{ $rate->label() }} — ₱{{ number_format($rate->price) }}
                                        </flux:select.option>
                                    @endforeach
                                @endif
                            </flux:select>
                        @endif

                        {{-- What the guest has actually chosen, spelled out. The old form only
                             showed a duration, which left guests guessing when they had to be out. --}}
                        @if ($this->startsAt() && $this->endsAt())
                            <div class="border border-sand-200 bg-white p-4">
                                <p class="eyebrow text-[10px] text-gold-600">
                                    {{ $this->isOvernight() ? __('Overnight stay') : __('Day use') }}
                                </p>

                                <dl class="mt-3 space-y-2 text-sm">
                                    {{-- Day use spread over separate days has no single
                                         check-in and check-out: the room is taken for the
                                         same hours on each of them and free in between. --}}
                                    @if (! $this->isOvernight() && $this->days > 1)
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-brand-800/60">{{ __('Each day') }}</dt>
                                            <dd class="text-right font-medium text-brand-900">
                                                {{ $this->startsAt()->format('g:i A') }}
                                                –
                                                {{ $this->startsAt()->copy()->addHours($this->stayHours)->format('g:i A') }}
                                            </dd>
                                        </div>

                                        <div class="flex justify-between gap-4">
                                            <dt class="text-brand-800/60">{{ __('Days') }}</dt>
                                            <dd class="text-right font-medium text-brand-900">{{ $this->datesLabel }}</dd>
                                        </div>
                                    @else
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-brand-800/60">{{ __('Check-in') }}</dt>
                                            <dd class="text-right font-medium text-brand-900">
                                                {{ $this->startsAt()->format('D, M j · g:i A') }}
                                            </dd>
                                        </div>

                                        <div class="flex justify-between gap-4">
                                            <dt class="text-brand-800/60">{{ __('Check-out') }}</dt>
                                            <dd class="text-right font-medium text-brand-900">
                                                {{ $this->endsAt()->format('D, M j · g:i A') }}
                                            </dd>
                                        </div>
                                    @endif

                                    <div class="flex justify-between gap-4 border-t border-sand-200 pt-2">
                                        <dt class="text-brand-800/60">{{ __('Length') }}</dt>
                                        <dd class="text-right font-medium text-brand-900">
                                            @if ($this->isOvernight())
                                                {{ trans_choice('{1} :count night|[2,*] :count nights', $this->nights, ['count' => $this->nights]) }}
                                            @elseif ($this->days > 1)
                                                {{ __(':hours each day', ['hours' => trans_choice('{1} :count hour|[2,*] :count hours', $this->stayHours, ['count' => $this->stayHours])]) }}
                                            @else
                                                {{ trans_choice('{1} :count hour|[2,*] :count hours', $this->stayHours, ['count' => $this->stayHours]) }}
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        @endif

                        <flux:radio.group wire:model.live="payment_option" :label="__('Payment option')" variant="segmented">
                            <flux:radio value="downpayment" :label="__('Downpayment (50%)')" />
                            <flux:radio value="full" :label="__('Pay in full')" />
                        </flux:radio.group>

                        @if ($this->arriveBy)
                            <div class="border-s-2 border-gold-400 bg-sand-100 p-4 text-sm text-brand-800">
                                {{ __('Please arrive by :time — :minutes minutes before your check-in.', [
                                    'time' => $this->arriveBy->format('g:i A'),
                                    'minutes' => \App\Models\Room::ARRIVE_EARLY_MINUTES,
                                ]) }}
                            </div>
                        @endif

                        <flux:separator variant="subtle" />

                        <flux:input wire:model="guest_name" :label="__('Full name')" :placeholder="__('Enter your full name')" required />

                        <flux:input
                            wire:model="guest_phone"
                            :label="__('Phone number')"
                            type="tel"
                            inputmode="numeric"
                            placeholder="09123456789"
                            required
                        />

                        <flux:input wire:model="guest_email" :label="__('Email address')" type="email" placeholder="you@email.com" required />

                        {{-- Live price summary --}}
                        @if ($this->quote)
                            <div class="border-s-2 border-gold-400 bg-sand-100 p-5">
                                <h3 class="text-sm font-semibold text-brand-900">{{ __('Price summary') }}</h3>

                                <dl class="mt-4 space-y-2.5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-brand-800/70">
                                            {{ $this->isOvernight()
                                                ? __('Room rate (₱:price × :nights)', [
                                                    'price' => number_format($this->rate->price),
                                                    'nights' => trans_choice('{1} :count night|[2,*] :count nights', $this->nights, ['count' => $this->nights]),
                                                ])
                                                : __('Room rate (:duration)', ['duration' => $this->rate->label()]) }}
                                        </dt>
                                        <dd class="font-medium text-brand-900">₱{{ number_format($this->quote['total']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4 border-t border-sand-200 pt-2.5">
                                        <dt class="font-semibold text-brand-900">{{ __('Total') }}</dt>
                                        <dd class="font-semibold text-brand-900">₱{{ number_format($this->quote['total']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <dt class="font-semibold text-brand-800">
                                            {{ $this->payingInFull() ? __('Paying now (100%)') : __('Paying now (50%)') }}
                                        </dt>
                                        <dd class="font-bold text-brand-800">₱{{ number_format($this->quote['amount_paid']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <dt class="text-brand-800/70">{{ __('Balance on arrival') }}</dt>
                                        <dd class="font-medium text-brand-900">₱{{ number_format($this->quote['balance']) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endif

                        <button
                            type="submit"
                            class="eyebrow w-full bg-brand-800 px-6 py-4 text-[11px] text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="proceedToPayment">{{ __('Proceed to payment') }}</span>
                            <span wire:loading wire:target="proceedToPayment">{{ __('Checking availability…') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

    @endif
</div>
