<?php

use App\Enums\BookingStatus;
use App\Livewire\BooksDateRangeComponent;
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
class extends BooksDateRangeComponent {
    public ?int $room_id = null;

    #[Url(as: 'entry')]
    public ?int $entry_hour = null;

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

    public string $guest_name = '';

    public string $guest_phone = '';

    public string $guest_email = '';

    /**
     * Whether the GCash panel is open, i.e. the form validated and we are waiting
     * for the guest to say they have sent the payment.
     */
    public bool $showPayment = false;

    /**
     * The reference of the booking just created, which switches the page to the
     * confirmation view.
     */
    public ?string $reference = null;

    /**
     * Prefill the contact fields for a signed-in guest, and take whatever the homepage's
     * availability bar sent over.
     */
    public function mount(): void
    {
        if ($user = Auth::user()) {
            $this->guest_name = $user->name;
            $this->guest_email = $user->email;
        }

        $this->discardUnusableSearch();
    }

    /**
     * Drop query-string values the form could never accept, so a stale link or a
     * hand-edited URL opens on an empty field instead of one the rules will reject.
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
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function (string $attribute, mixed $value, callable $fail) {
                    if ($this->nights > 0 && $this->room && ! $this->room->sellsOvernightStays()) {
                        $fail(__('This room is for day use only. Pick a single day, or choose another room to stay the night.'));
                    }
                },
            ],
            'entry_hour' => ['required', 'integer', 'min:'.Room::ENTRY_OPENS_AT, 'max:'.Room::ENTRY_CLOSES_AT],

            // Only day-use stays need a duration; overnight ones are sold by the night.
            'rate_id' => [
                Rule::requiredIf(fn () => $this->nights === 0),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, callable $fail) {
                    if ($this->room_id && ! RoomRate::where('id', $value)->where('room_id', $this->room_id)->exists()) {
                        $fail(__('Choose one of the durations offered for this room.'));
                    }
                },
            ],
            'payment_option' => ['required', 'in:downpayment,full'],
            'guest_name' => ['required', 'string', 'min:2', 'max:100'],
            'guest_phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'guest_email' => ['required', 'email:rfc', 'max:255'],
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
            'start_date.required' => __('Pick your dates on the calendar.'),
            'start_date.after_or_equal' => __('Pick a check-in date from today onwards.'),
            'end_date.after_or_equal' => __('Check-out cannot come before check-in.'),
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
     */
    public function isOvernight(): bool
    {
        return $this->nights > 0;
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
        return $this->room && $this->rate
            ? $this->room->quote($this->rate, $this->payingInFull(), $this->nights)
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

        $this->resetValidation(['room_id', 'end_date', 'rate_id']);

        // Availability is per room, so the calendar has to be rebuilt for the new one.
        unset($this->availability, $this->rate, $this->quote, $this->stayHours);
    }

    /**
     * Drop a day-use duration once the guest starts staying the night, so a stale rate
     * never lingers behind the hidden selector.
     */
    protected function afterDateRangeChange(): void
    {
        if ($this->isOvernight()) {
            $this->rate_id = null;
        }

        unset($this->rate, $this->quote, $this->stayHours);
    }

    /**
     * Validate the form and open the GCash panel.
     */
    public function proceedToPayment(): void
    {
        $this->validate();
        $this->assertRoomIsAvailable();

        $this->showPayment = true;
    }

    /**
     * Close the GCash panel without booking.
     */
    public function cancelPayment(): void
    {
        $this->showPayment = false;
    }

    /**
     * Record the booking as pending once the guest says the payment is sent.
     */
    public function confirmPayment(): void
    {
        $this->validate();

        $quote = $this->room->quote($this->rate, $this->payingInFull(), $this->nights);

        // Two guests can reach this point for the same room at once, so the last check
        // runs inside the transaction that writes the booking, holding the rows it read.
        $booking = DB::transaction(function () use ($quote) {
            $this->assertRoomIsAvailable(lock: true);

            return RoomBooking::create([
                'reference' => RoomBooking::generateReference(),
                'room_id' => $this->room_id,
                'user_id' => Auth::id(),
                'guest_name' => $this->guest_name,
                'guest_phone' => $this->guest_phone,
                'guest_email' => $this->guest_email,
                'starts_at' => $this->startsAt(),
                'ends_at' => $this->endsAt(),
                'hours' => $this->stayHours,
                'nights' => $this->nights,
                'pay_in_full' => $this->payingInFull(),
                ...$quote,
                'status' => BookingStatus::Pending,
            ]);
        });

        $booking->sendPlacementNotifications();

        $this->showPayment = false;
        $this->reference = $booking->reference;
    }

    /**
     * Start over on a fresh booking form.
     */
    public function bookAnother(): void
    {
        $this->reset(['room_id', 'entry_hour', 'rate_id', 'preferred_hours', 'payment_option', 'reference', 'showPayment']);
        $this->resetDateRange();
        $this->mount();
    }

    /**
     * The start of the requested stay, or null while the date or time is missing.
     */
    public function startsAt(): ?Carbon
    {
        if ($this->start_date === '' || $this->entry_hour === null) {
            return null;
        }

        return Carbon::parse($this->start_date)->setTime($this->entry_hour, 0);
    }

    /**
     * When the guest checks out.
     *
     * An overnight guest leaves at their entry time on the check-out date, so a stay
     * from the 10th to the 13th entering at 2PM runs to 2PM on the 13th. A day-use guest
     * leaves once their chosen block is up.
     */
    public function endsAt(): ?Carbon
    {
        $startsAt = $this->startsAt();

        if (! $startsAt || ! $this->rate) {
            return null;
        }

        return $this->isOvernight()
            ? $startsAt->copy()->addDays($this->nights)
            : $startsAt->copy()->addHours($this->rate->hours);
    }

    /**
     * Reject the stay if a pending or confirmed booking already overlaps it.
     *
     * The comparison is half-open on both ends, so one guest checking out at the same
     * hour another checks in is not a clash.
     */
    protected function assertRoomIsAvailable(bool $lock = false): void
    {
        $query = RoomBooking::query()
            ->blocking()
            ->where('room_id', $this->room_id)
            ->where('starts_at', '<', $this->endsAt())
            ->where('ends_at', '>', $this->startsAt());

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($query->exists()) {
            $this->showPayment = false;

            unset($this->availability);

            throw ValidationException::withMessages([
                'start_date' => __('That room is taken for part of this stay. Please pick another time, date, or room.'),
            ]);
        }
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
                        <div>
                            <x-booking.availability-calendar
                                :month="$this->calendar"
                                :start="$start_date"
                                :end="$end_date"
                                :availability="$this->availability"
                                :label="__('Check-in and check-out')"
                                :hint="$this->room
                                    ? __('Tap one day to book the room for the day. Tap a later day to stay the night.')
                                    : __('Pick a room first to see which dates are still open.')"
                            />

                            @error('start_date')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                            @error('end_date')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:select wire:model.live="entry_hour" :label="__('Time of entry')" :placeholder="__('Select entry time')">
                            @foreach ($this->entryHours as $hour => $label)
                                <flux:select.option wire:key="entry-{{ $hour }}" :value="$hour">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        {{-- Day use is sold by the hour block; a stay over one or more nights
                             is always sold at the room's nightly rate, so the duration
                             selector only makes sense for the former. --}}
                        @if (! $this->isOvernight())
                            <flux:select
                                wire:model.live="rate_id"
                                :label="__('How long for')"
                                :placeholder="$this->room ? __('Select hours') : __('Pick a room first')"
                                :disabled="! $this->room"
                            >
                                @if ($this->room)
                                    @foreach ($this->room->rates as $rate)
                                        <flux:select.option wire:key="rate-{{ $rate->id }}" :value="$rate->id">
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

                                    <div class="flex justify-between gap-4 border-t border-sand-200 pt-2">
                                        <dt class="text-brand-800/60">{{ __('Length') }}</dt>
                                        <dd class="text-right font-medium text-brand-900">
                                            {{ $this->isOvernight()
                                                ? trans_choice('{1} :count night|[2,*] :count nights', $this->nights, ['count' => $this->nights])
                                                : trans_choice('{1} :count hour|[2,*] :count hours', $this->stayHours, ['count' => $this->stayHours]) }}
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

        {{-- GCash panel --}}
        @if ($showPayment && $this->quote)
            <div
                class="fixed inset-0 z-60 flex items-end justify-center bg-brand-950/70 p-4 backdrop-blur-sm sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="payment-title"
            >
                <div class="max-h-full w-full max-w-md overflow-y-auto border-t-2 border-gold-400 bg-white p-6 shadow-2xl sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <h2 id="payment-title" class="font-serif text-2xl font-medium text-brand-900">{{ __('GCash payment') }}</h2>

                        <button
                            type="button"
                            wire:click="cancelPayment"
                            class="-me-2 -mt-1 flex size-9 items-center justify-center text-brand-800/60 transition-colors hover:bg-sand-100 hover:text-brand-900"
                        >
                            <span class="sr-only">{{ __('Close') }}</span>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <p class="mt-4 text-sm text-brand-800/70">
                        {{ $this->payingInFull() ? __('Send the full amount to hold your room:') : __('Send this amount to hold your room:') }}
                    </p>

                    <p class="mt-1 font-serif text-5xl font-medium text-brand-800">₱{{ number_format($this->quote['amount_paid']) }}</p>

                    <dl class="mt-6 space-y-2.5 bg-sand-100 p-5 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-brand-800/60">{{ __('Merchant') }}</dt>
                            <dd class="font-medium text-brand-900">{{ config('app.name') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-brand-800/60">{{ __('GCash number') }}</dt>
                            <dd class="font-medium text-brand-900">{{ config('resort.gcash.number') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-brand-800/60">{{ __('Account name') }}</dt>
                            <dd class="font-medium text-brand-900">{{ config('resort.gcash.account_name') }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-sm text-brand-800/70">
                        {{ __('Send the exact amount, then keep a screenshot of your receipt — we will ask for it if we cannot match your payment.') }}
                    </p>

                    <button
                        type="button"
                        wire:click="confirmPayment"
                        class="mt-6 eyebrow w-full bg-brand-800 px-6 py-4 text-[11px] text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
                        wire:loading.attr="disabled"
                        wire:target="confirmPayment"
                    >
                        <span wire:loading.remove wire:target="confirmPayment">{{ __('I have sent the payment') }}</span>
                        <span wire:loading wire:target="confirmPayment">{{ __('Saving your booking…') }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="cancelPayment"
                        class="eyebrow mt-3 w-full px-6 py-3.5 text-[11px] text-brand-800/70 transition-colors hover:bg-sand-100"
                    >
                        {{ __('Go back') }}
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
