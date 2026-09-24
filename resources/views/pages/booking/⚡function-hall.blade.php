<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Livewire\BooksDatesComponent;
use App\Models\Booking;
use App\Models\Hall;
use App\Support\Availability;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::marketing')]
#[Title('Book a Function Hall')]
class extends BooksDatesComponent {
    public ?int $hall_id = null;

    public ?int $start_hour = null;

    public ?int $end_hour = null;

    public bool $include_skirting = true;

    /**
     * Take the contact details of a signed-in guest, and whatever a guest coming back
     * from PayMongo brought with them.
     */
    public function mount(): void
    {
        $this->prefillFromSession();
        $this->discardUnusableDate();
    }

    /**
     * How the payment routes name this page.
     */
    protected function reservationType(): string
    {
        return 'hall';
    }

    /**
     * Which dates the chosen hall is already spoken for. Nothing is closed off until a
     * hall is picked, since availability is per hall.
     */
    protected function availabilityFor(CarbonInterface $from, CarbonInterface $until): Availability
    {
        return $this->hall_id
            ? Availability::forHall($this->hall_id, $from, $until)
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
            'hall_id' => ['required', 'integer', 'exists:halls,id'],
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
                },
            ],
            'start_hour' => ['required', 'integer', 'min:'.Hall::OPENS_AT, 'max:'.(Hall::CLOSES_AT - Hall::MINIMUM_HOURS)],
            'end_hour' => [
                'required',
                'integer',
                'gt:start_hour',
                'max:'.Hall::CLOSES_AT,
                function (string $attribute, mixed $value, callable $fail) {
                    if ($this->start_hour === null) {
                        return;
                    }

                    if (($value - $this->start_hour) < Hall::MINIMUM_HOURS) {
                        $fail(__('A hall is booked for at least :hours hours.', ['hours' => Hall::MINIMUM_HOURS]));
                    }
                },
            ],
            'include_skirting' => ['boolean'],
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
            'hall_id.required' => __('Choose a function hall first.'),
            'dates.required' => __('Pick at least one date on the calendar.'),
            'dates.max' => __('A booking can cover at most :count days.', ['count' => self::MAX_DATES]),
            'end_hour.gt' => __('The end time has to be after the start time.'),
            'guest_phone.regex' => __('Enter an 11-digit mobile number starting with 09, e.g. 09123456789.'),
        ];
    }

    /**
     * The halls a guest can currently book.
     *
     * @return Collection<int, Hall>
     */
    #[Computed]
    public function halls(): Collection
    {
        return Hall::query()->active()->with('photos')->get();
    }

    /**
     * The hall the guest has selected, if any.
     */
    #[Computed]
    public function hall(): ?Hall
    {
        return $this->hall_id ? $this->halls->firstWhere('id', $this->hall_id) : null;
    }

    /**
     * The live price breakdown, once enough of the form is filled in to compute one.
     *
     * @return array{blocks: int, rent_total: int, skirting_total: int, total: int, downpayment: int, balance: int}|null
     */
    #[Computed]
    public function quote(): ?array
    {
        if (! $this->hall || ! $this->hours || ! $this->hasDates()) {
            return null;
        }

        return $this->hall->quote($this->hours, $this->include_skirting, $this->days);
    }

    /**
     * The requested length of stay, or null while the time range is incomplete or invalid.
     */
    #[Computed]
    public function hours(): ?int
    {
        if ($this->start_hour === null || $this->end_hour === null) {
            return null;
        }

        $hours = $this->end_hour - $this->start_hour;

        return $hours >= Hall::MINIMUM_HOURS ? $hours : null;
    }

    /**
     * Selectable start times.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function startHours(): array
    {
        return $this->hourOptions(Hall::OPENS_AT, Hall::CLOSES_AT - Hall::MINIMUM_HOURS);
    }

    /**
     * Selectable end times — any whole hour from the minimum booking length onwards.
     *
     * Stepping by the hour rather than by the block is what lets a guest book 7:00 AM to
     * 12:00 PM; billing still rounds those five hours up to two blocks.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function endHours(): array
    {
        if ($this->start_hour === null) {
            return [];
        }

        $options = [];

        for ($hour = $this->start_hour + Hall::MINIMUM_HOURS; $hour <= Hall::CLOSES_AT; $hour++) {
            $options[$hour] = $this->formatHour($hour);
        }

        return $options;
    }

    /**
     * The booking created by this session, once payment has been acknowledged.
     */
    #[Computed]
    public function booking(): ?Booking
    {
        return $this->reference ? Booking::with('hall')->where('reference', $this->reference)->first() : null;
    }

    /**
     * Clear the end time whenever it no longer lines up with a new start time.
     */
    public function updatedStartHour(): void
    {
        if ($this->end_hour !== null && ! array_key_exists($this->end_hour, $this->endHours)) {
            $this->end_hour = null;
        }
    }

    /**
     * Select a hall.
     */
    public function selectHall(int $hallId): void
    {
        $this->hall_id = $hallId;
        $this->resetValidation('hall_id');

        // Availability is per hall, so the calendar has to be rebuilt for the new one.
        unset($this->availability);
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

        $validated = $this->validate();
        unset($validated['dates']);

        $this->assertSlotIsAvailable();

        $quote = $this->hall->quote($this->hours, $this->include_skirting, $this->days);

        $this->assertAmountIsPayable($quote['downpayment']);

        // Two guests can reach this point for the same slot at once, so the last check
        // runs inside the transaction that writes the booking, behind the hall's row lock.
        $booking = DB::transaction(function () use ($validated, $quote) {
            $this->assertSlotIsAvailable(lock: true);

            $booking = Booking::create([
                ...$validated,
                'reference' => Booking::generateReference(),
                'user_id' => Auth::id(),
                // Overwritten by syncDates() below; set here because the columns are
                // NOT NULL and the row has to exist before its days can be written.
                'start_date' => $this->firstDate(),
                'end_date' => $this->lastDate(),
                'hours' => $this->hours,
                'days' => $this->days,
                'rent_total' => $quote['rent_total'],
                'skirting_total' => $quote['skirting_total'],
                'total' => $quote['total'],
                'downpayment' => $quote['downpayment'],
                'balance' => $quote['balance'],
                'status' => BookingStatus::Pending,
                'payment_provider' => 'paymongo',
                'payment_status' => PaymentStatus::Awaiting,
                'payment_expires_at' => now()->addMinutes((int) config('services.paymongo.hold_minutes')),
            ]);

            $booking->syncDates($this->bookedDates());

            return $booking;
        });

        return $this->sendToCheckout($booking, $quote['downpayment'], $this->hall->name);
    }

    /**
     * Start over on a fresh booking form.
     */
    public function bookAnother(): void
    {
        $this->reset(['hall_id', 'start_hour', 'end_hour', 'reference', 'paymentError']);
        $this->resetDates();
        $this->include_skirting = true;
        $this->mount();
    }

    /**
     * Reject the booking if an existing one already overlaps any day of it.
     *
     * The same hours are held on every day of a range, so a clash is a range that
     * overlaps on dates *and* on hours.
     */
    protected function assertSlotIsAvailable(bool $lock = false): void
    {
        if ($lock) {
            // Everyone booking this hall queues behind its row, so the check below and the
            // insert that follows cannot interleave with another guest's. Locking the
            // booking query instead would be a bet on gap locks in an empty result.
            Hall::query()->whereKey($this->hall_id)->lockForUpdate()->first();
        }

        $clashing = Booking::query()
            ->blocking()
            ->where('hall_id', $this->hall_id)
            // Half-open on both ends, so an event ending as another begins is no clash.
            ->where('start_hour', '<', $this->end_hour)
            ->where('end_hour', '>', $this->start_hour);

        if (Availability::takenDates($clashing, $this->bookedDates()) !== []) {
            unset($this->availability);

            throw ValidationException::withMessages([
                'dates' => __('That hall is already booked at this time on one of your dates. Remove that date, or pick another time.'),
            ]);
        }
    }

    /**
     * Build a list of hour => label options.
     *
     * @return array<int, string>
     */
    protected function hourOptions(int $from, int $to): array
    {
        $options = [];

        for ($hour = $from; $hour <= $to; $hour++) {
            $options[$hour] = $this->formatHour($hour);
        }

        return $options;
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
                        @if ($this->booking->payment_status === \App\Enums\PaymentStatus::Paid)
                            {{ __('Your payment has gone through and your booking is confirmed. We have emailed you a copy.') }}
                        @else
                            {{ __('We are waiting for your payment to clear. You will get an email the moment it does.') }}
                        @endif
                    </p>

                    <dl class="mt-8 divide-y divide-sand-200 border-y border-sand-200 text-sm">
                        @php
                            $booking = $this->booking;
                            $rows = [
                                __('Reference') => $booking->reference,
                                ...($booking->payment_reference ? [__('Payment reference') => $booking->payment_reference] : []),
                                __('Hall') => $booking->hall->name,
                                trans_choice('{1} Date|[2,*] Dates', $booking->days) => \App\Support\DateList::label($booking->dateList())
                                    .($booking->days > 1 ? ' ('.trans_choice('{1} :count day|[2,*] :count days', $booking->days, ['count' => $booking->days]).')' : ''),
                                __('Time') => $this->formatHour($booking->start_hour).' – '.$this->formatHour($booking->end_hour)
                                    .' ('.trans_choice('{1} :count hour|[2,*] :count hours', $booking->hours, ['count' => $booking->hours])
                                    .($booking->days > 1 ? __(' each day') : '').')',
                                __('Name') => $booking->guest_name,
                                __('Phone') => $booking->guest_phone,
                                __('Email') => $booking->guest_email,
                                __('Skirting') => $booking->include_skirting ? __('Included') : __('Not included'),
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
                            <dt class="text-brand-800/60">{{ __('Downpayment sent') }}</dt>
                            <dd class="text-right font-semibold text-brand-800">₱{{ number_format($booking->downpayment) }}</dd>
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
                            {{ __('Book another hall') }}
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
                src="{{ asset('images/function-hall/function-hall-1.jpg') }}"
                alt="{{ __('The function hall with draped ceiling and stage backdrop') }}"
                width="1600"
                height="1200"
                fetchpriority="high"
                decoding="async"
                class="absolute inset-0 size-full object-cover opacity-60"
            />
            <div aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-brand-950 via-brand-950/50 to-brand-950/20"></div>

            <div class="relative mx-auto w-full max-w-7xl px-4 pb-10 sm:px-6 lg:px-8 lg:pb-14">
                <p class="eyebrow text-gold-300">{{ __('Function halls') }}</p>

                <h1 class="mt-4 font-serif text-4xl/tight font-medium text-balance text-white sm:text-5xl/tight">
                    {{ __('Book a function hall') }}
                </h1>

                <p class="mt-4 max-w-2xl text-base/7 text-pretty text-sand-100/80">
                    {{ __('Pick your venue and time between 7:00 AM and 10:00 PM, then hold the date with a 50% downpayment.') }}
                </p>
            </div>
        </section>

        <section class="bg-sand-50 pb-24 pt-12 lg:pb-32">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:items-start lg:gap-8 lg:px-8">
                {{-- Hall picker --}}
                <div class="min-w-0 bg-white p-6 shadow-sm shadow-brand-950/5 ring-1 ring-sand-200 sm:p-8">
                    <h2 class="font-serif text-2xl font-medium text-brand-900">{{ __('Select a function hall') }}</h2>

                    @if ($this->halls->isEmpty())
                        <p class="mt-6 border border-dashed border-sand-200 p-8 text-center text-sm text-brand-800/60">
                            {{ __('No function halls are available to book right now. Please check back later.') }}
                        </p>
                    @else
                        <div class="mt-6 space-y-4" role="radiogroup" aria-label="{{ __('Function halls') }}">
                            @foreach ($this->halls as $hall)
                                <button
                                    type="button"
                                    wire:key="hall-{{ $hall->id }}"
                                    wire:click="selectHall({{ $hall->id }})"
                                    role="radio"
                                    aria-checked="{{ $hall_id === $hall->id ? 'true' : 'false' }}"
                                    @class([
                                        'w-full border p-5 text-left transition',
                                        'border-brand-600 bg-brand-50 ring-1 ring-brand-600' => $hall_id === $hall->id,
                                        'border-sand-200 bg-white hover:border-gold-300 hover:bg-sand-50' => $hall_id !== $hall->id,
                                    ])
                                >
                                        @if ($hall->photos->isNotEmpty())
                                            {{-- No dots: this card is a <button>, and nested
                                                 interactive elements would both be invalid HTML
                                                 and steal the click that selects the hall. --}}
                                            <x-marketing.photo-slideshow
                                                :photos="$hall->photoSlides()"
                                                :dots="false"
                                                class="mb-4 aspect-3/2"
                                            />
                                        @endif

                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <h3 class="font-serif text-2xl font-medium text-brand-900">{{ $hall->name }}</h3>
                                            <p class="mt-1.5 text-sm/6 text-brand-800/70">{{ $hall->description }}</p>
                                        </div>

                                        <span @class([
                                            'mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                                            'border-brand-600 bg-brand-600 text-white' => $hall_id === $hall->id,
                                            'border-sand-200' => $hall_id !== $hall->id,
                                        ])>
                                            @if ($hall_id === $hall->id)
                                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                                                </svg>
                                            @endif
                                        </span>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2 border-t border-sand-200 pt-4">
                                        <span class="bg-brand-800 px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ __('Rent: ₱:price / :hours hours', ['price' => number_format($hall->rent_price), 'hours' => \App\Models\Hall::HOURS_PER_BLOCK]) }}
                                        </span>
                                        <span class="bg-gold-500 px-3 py-1.5 text-xs font-semibold text-brand-950">
                                            {{ __('Skirting: ₱:price', ['price' => number_format($hall->skirting_price)]) }}
                                        </span>
                                        <span class="bg-sand-100 px-3 py-1.5 text-xs font-semibold text-brand-800/70">
                                            {{ trans_choice('{1} :count guest|[2,*] up to :count guests', $hall->capacity, ['count' => number_format($hall->capacity)]) }}
                                        </span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @error('hall_id')
                        <p class="mt-4 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Booking details --}}
                <div class="min-w-0 border-t-2 border-gold-400 bg-white p-6 shadow-sm shadow-brand-950/5 ring-1 ring-sand-200 sm:p-8 lg:sticky lg:top-28 lg:max-h-[calc(100dvh-8.5rem)] lg:overflow-y-auto">
                    <h2 class="font-serif text-2xl font-medium text-brand-900">{{ __('Booking details') }}</h2>

                    <x-booking.selection
                        class="mt-5"
                        :name="$this->hall?->name"
                        :prompt="__('Pick a hall from the list to get started.')"
                        :facts="$this->hall ? [
                            __('₱:price / :hours hrs', ['price' => number_format($this->hall->rent_price), 'hours' => \App\Models\Hall::HOURS_PER_BLOCK]),
                            __('Skirting ₱:price', ['price' => number_format($this->hall->skirting_price)]),
                            trans_choice('{1} up to :count guest|[2,*] up to :count guests', $this->hall->capacity, ['count' => number_format($this->hall->capacity)]),
                        ] : []"
                    />
                    <form wire:submit="proceedToPayment" class="mt-6 space-y-6">
                        <div>
                            <x-booking.availability-calendar
                                :month="$this->calendar"
                                :dates="$dates"
                                :availability="$this->availability"
                                :label="__('Select your dates')"
                                :hint="$this->hall
                                    ? __('Tap each day you need. Tap it again to remove it — the days need not be in a row.')
                                    : __('Pick a hall first to see which dates are still open.')"
                            />

                            @if ($this->hasDates())
                                <p class="mt-2 text-sm font-medium text-brand-900">
                                    {{ $this->datesLabel }}
                                    @if ($this->days > 1)
                                        <span class="text-brand-800/60">
                                            {{ trans_choice('{1} · :count day|[2,*] · :count days', $this->days, ['count' => $this->days]) }}
                                        </span>
                                    @endif
                                </p>
                            @endif

                            @error('dates')
                                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                {{-- A real empty option rather than Flux's `placeholder`,
                                     which renders as `<option disabled selected>`. A
                                     disabled option is not a valid selection, so after a
                                     Livewire re-render the browser fell back to the first
                                     real time and the field looked filled in when it was not. --}}
                                <flux:select wire:model.live="start_hour" :label="__('Start time')">
                                    <flux:select.option value="">{{ __('Please select a start time') }}</flux:select.option>

                                    @foreach ($this->startHours as $hour => $label)
                                        <flux:select.option :value="$hour">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:select
                                    wire:model.live="end_hour"
                                    :label="__('End time')"
                                    :disabled="$start_hour === null"
                                >
                                    <flux:select.option value="">
                                        {{ $start_hour === null ? __('Pick a start time first') : __('Please select an end time') }}
                                    </flux:select.option>

                                    @foreach ($this->endHours as $hour => $label)
                                        <flux:select.option :value="$hour">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <p class="mt-2 text-xs text-brand-800/60">
                                {{ __('Open 7:00 AM to 10:00 PM. Minimum booking is 4 hours, billed in 4-hour blocks — a 5-hour booking is charged as 2 blocks.') }}
                                @if ($this->days > 1)
                                    {{ __('These hours are held on each of your :count days.', ['count' => $this->days]) }}
                                @endif
                            </p>
                        </div>

                        <flux:switch
                            wire:model.live="include_skirting"
                            :label="__('Include skirting')"
                            :description="$this->days > 1
                                ? __('Skirting and setup, charged for each day of your booking.')
                                : __('One-time skirting and setup fee.')"
                        />

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
                                            {{ $this->days > 1
                                                ? __('Rent (₱:rate × :blocks × :days)', [
                                                    'rate' => number_format($this->hall->rent_price),
                                                    'blocks' => trans_choice('{1} :count block|[2,*] :count blocks', $this->quote['blocks'], ['count' => $this->quote['blocks']]),
                                                    'days' => trans_choice('{1} :count day|[2,*] :count days', $this->days, ['count' => $this->days]),
                                                ])
                                                : __('Rent (₱:rate × :blocks)', [
                                                    'rate' => number_format($this->hall->rent_price),
                                                    'blocks' => trans_choice('{1} :count block|[2,*] :count blocks', $this->quote['blocks'], ['count' => $this->quote['blocks']]),
                                                ]) }}
                                        </dt>
                                        <dd class="font-medium text-brand-900">₱{{ number_format($this->quote['rent_total']) }}</dd>
                                    </div>

                                    {{-- Without this, a 5-hour and an 8-hour booking costing the same reads as a bug. --}}
                                    @if ($this->hours % \App\Models\Hall::HOURS_PER_BLOCK !== 0)
                                        <p class="text-xs text-brand-800/60">
                                            {{ __('Your :hours hours run into a block of :block hours, which is billed whole.', [
                                                'hours' => $this->hours,
                                                'block' => \App\Models\Hall::HOURS_PER_BLOCK,
                                            ]) }}
                                        </p>
                                    @endif

                                    @if ($this->quote['skirting_total'] > 0)
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-brand-800/70">
                                                {{ $this->days > 1
                                                    ? __('Skirting and setup (× :days)', ['days' => trans_choice('{1} :count day|[2,*] :count days', $this->days, ['count' => $this->days])])
                                                    : __('Skirting and setup') }}
                                            </dt>
                                            <dd class="font-medium text-brand-900">₱{{ number_format($this->quote['skirting_total']) }}</dd>
                                        </div>
                                    @endif

                                    <div class="flex justify-between gap-4 border-t border-sand-200 pt-2.5">
                                        <dt class="font-semibold text-brand-900">{{ __('Total') }}</dt>
                                        <dd class="font-semibold text-brand-900">₱{{ number_format($this->quote['total']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <dt class="font-semibold text-brand-800">{{ __('Downpayment (50%)') }}</dt>
                                        <dd class="font-bold text-brand-800">₱{{ number_format($this->quote['downpayment']) }}</dd>
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
                            <span wire:loading.remove wire:target="proceedToPayment">{{ __('Pay :amount and book', ['amount' => $this->quote ? '₱'.number_format($this->quote['downpayment']) : '']) }}</span>
                            <span wire:loading wire:target="proceedToPayment">{{ __('Taking you to the payment page…') }}</span>
                        </button>

                        <p class="text-center text-xs text-brand-800/60">
                            {{ __('You will be taken to PayMongo to pay by GCash or Maya. Your dates are held while you pay.') }}
                        </p>
                    </form>
                </div>
            </div>
        </section>

    @endif
</div>
