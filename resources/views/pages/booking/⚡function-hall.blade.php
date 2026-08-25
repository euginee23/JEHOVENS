<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::marketing')]
#[Title('Book a Function Hall')]
class extends Component {
    public ?int $hall_id = null;

    public string $booking_date = '';

    public ?int $start_hour = null;

    public ?int $end_hour = null;

    public bool $include_skirting = true;

    public string $guest_name = '';

    public string $guest_phone = '';

    public string $guest_email = '';

    /**
     * Whether the GCash panel is open, i.e. the form validated and we are waiting
     * for the guest to say they have sent the downpayment.
     */
    public bool $showPayment = false;

    /**
     * The reference of the booking just created, which switches the page to the
     * confirmation view.
     */
    public ?string $reference = null;

    /**
     * Prefill the contact fields for a signed-in guest.
     */
    public function mount(): void
    {
        if ($user = Auth::user()) {
            $this->guest_name = $user->name;
            $this->guest_email = $user->email;
        }
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
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_hour' => ['required', 'integer', 'min:'.Hall::OPENS_AT, 'max:'.(Hall::CLOSES_AT - Hall::HOURS_PER_BLOCK)],
            'end_hour' => [
                'required',
                'integer',
                'gt:start_hour',
                'max:'.Hall::CLOSES_AT,
                function (string $attribute, mixed $value, callable $fail) {
                    if ($this->start_hour === null) {
                        return;
                    }

                    if (($value - $this->start_hour) % Hall::HOURS_PER_BLOCK !== 0) {
                        $fail(__('Halls are rented in blocks of :hours hours, so pick 4, 8, or 12 hours.', ['hours' => Hall::HOURS_PER_BLOCK]));
                    }
                },
            ],
            'include_skirting' => ['boolean'],
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
            'hall_id.required' => __('Choose a function hall first.'),
            'booking_date.after_or_equal' => __('Pick a date from today onwards.'),
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
        if (! $this->hall || ! $this->hours) {
            return null;
        }

        return $this->hall->quote($this->hours, $this->include_skirting);
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

        return $hours > 0 && $hours % Hall::HOURS_PER_BLOCK === 0 ? $hours : null;
    }

    /**
     * Selectable start times.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function startHours(): array
    {
        return $this->hourOptions(Hall::OPENS_AT, Hall::CLOSES_AT - Hall::HOURS_PER_BLOCK);
    }

    /**
     * Selectable end times — always a whole number of blocks after the start time.
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

        for ($hour = $this->start_hour + Hall::HOURS_PER_BLOCK; $hour <= Hall::CLOSES_AT; $hour += Hall::HOURS_PER_BLOCK) {
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
    }

    /**
     * Validate the form and open the GCash panel.
     */
    public function proceedToPayment(): void
    {
        $this->validate();
        $this->assertSlotIsAvailable();

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
     * Record the booking as pending once the guest says the downpayment is sent.
     */
    public function confirmPayment(): void
    {
        $validated = $this->validate();
        $this->assertSlotIsAvailable();

        $hall = $this->hall;
        $quote = $hall->quote($this->hours, $this->include_skirting);

        $booking = Booking::create([
            ...$validated,
            'reference' => Booking::generateReference(),
            'user_id' => Auth::id(),
            'hours' => $this->hours,
            'rent_total' => $quote['rent_total'],
            'skirting_total' => $quote['skirting_total'],
            'total' => $quote['total'],
            'downpayment' => $quote['downpayment'],
            'balance' => $quote['balance'],
            'status' => BookingStatus::Pending,
        ]);

        $this->showPayment = false;
        $this->reference = $booking->reference;
    }

    /**
     * Start over on a fresh booking form.
     */
    public function bookAnother(): void
    {
        $this->reset(['hall_id', 'booking_date', 'start_hour', 'end_hour', 'reference', 'showPayment']);
        $this->include_skirting = true;
        $this->mount();
    }

    /**
     * Reject the slot if a pending or confirmed booking already overlaps it.
     */
    protected function assertSlotIsAvailable(): void
    {
        $clashes = Booking::query()
            ->blocking()
            ->where('hall_id', $this->hall_id)
            ->whereDate('booking_date', $this->booking_date)
            ->where('start_hour', '<', $this->end_hour)
            ->where('end_hour', '>', $this->start_hour)
            ->exists();

        if ($clashes) {
            $this->showPayment = false;

            throw ValidationException::withMessages([
                'booking_date' => __('That hall is already booked for part of this time slot. Please pick another time or date.'),
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
        <section class="relative isolate overflow-hidden bg-white">
            <x-marketing.glow />

            <div class="relative mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                <div class="rounded-3xl border border-zinc-200 bg-white p-8 shadow-xl shadow-brand-900/10 sm:p-10">
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
                        <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                        </svg>
                    </div>

                    <h1 class="mt-6 text-3xl font-bold tracking-tight text-zinc-900">{{ __('Booking received') }}</h1>

                    <p class="mt-3 text-zinc-600">
                        {{ __('We are verifying your downpayment. You will get a confirmation once it clears — usually within 24 hours.') }}
                    </p>

                    <dl class="mt-8 divide-y divide-zinc-200 border-y border-zinc-200 text-sm">
                        @php
                            $booking = $this->booking;
                            $rows = [
                                __('Reference') => $booking->reference,
                                __('Hall') => $booking->hall->name,
                                __('Date') => $booking->booking_date->format('F j, Y'),
                                __('Time') => $this->formatHour($booking->start_hour).' – '.$this->formatHour($booking->end_hour).' ('.trans_choice('{1} :count hour|[2,*] :count hours', $booking->hours, ['count' => $booking->hours]).')',
                                __('Name') => $booking->guest_name,
                                __('Phone') => $booking->guest_phone,
                                __('Email') => $booking->guest_email,
                                __('Skirting') => $booking->include_skirting ? __('Included') : __('Not included'),
                            ];
                        @endphp

                        @foreach ($rows as $label => $value)
                            <div class="flex justify-between gap-6 py-3" wire:key="row-{{ $loop->index }}">
                                <dt class="text-zinc-500">{{ $label }}</dt>
                                <dd class="text-right font-medium text-zinc-900">{{ $value }}</dd>
                            </div>
                        @endforeach

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-zinc-500">{{ __('Total') }}</dt>
                            <dd class="text-right font-medium text-zinc-900">₱{{ number_format($booking->total) }}</dd>
                        </div>

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-zinc-500">{{ __('Downpayment sent') }}</dt>
                            <dd class="text-right font-semibold text-brand-700">₱{{ number_format($booking->downpayment) }}</dd>
                        </div>

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-zinc-500">{{ __('Balance on arrival') }}</dt>
                            <dd class="text-right font-medium text-zinc-900">₱{{ number_format($booking->balance) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="bookAnother"
                            class="rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                        >
                            {{ __('Book another hall') }}
                        </button>

                        <a
                            href="{{ route('home') }}"
                            class="rounded-xl border border-zinc-300 bg-white px-6 py-3.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50"
                        >
                            {{ __('Back to home') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @else
        {{-- Page header --}}
        <section class="relative isolate overflow-hidden bg-white">
            <x-marketing.glow />

            <div class="relative mx-auto max-w-3xl px-4 pb-10 pt-14 text-center sm:px-6 lg:px-8 lg:pt-20">
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">{{ __('Function halls') }}</p>

                <h1 class="mt-3 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl">
                    {{ __('Book a function hall') }}
                </h1>

                <p class="mx-auto mt-5 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                    {{ __('Pick your venue and time between 7:00 AM and 10:00 PM, then hold the date with a 50% downpayment.') }}
                </p>
            </div>
        </section>

        <section class="bg-zinc-50 pb-20 pt-10 lg:pb-28">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:items-start lg:gap-8 lg:px-8">
                {{-- Hall picker --}}
                <div class="min-w-0 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('Select a function hall') }}</h2>

                    @if ($this->halls->isEmpty())
                        <p class="mt-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
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
                                        'w-full rounded-2xl border p-5 text-left transition',
                                        'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' => $hall_id === $hall->id,
                                        'border-zinc-200 bg-white hover:border-brand-300 hover:bg-zinc-50' => $hall_id !== $hall->id,
                                    ])
                                >
                                        @if ($hall->photos->isNotEmpty())
                                            {{-- No dots: this card is a <button>, and nested
                                                 interactive elements would both be invalid HTML
                                                 and steal the click that selects the hall. --}}
                                            <x-marketing.photo-slideshow
                                                :photos="$hall->photoSlides()"
                                                :dots="false"
                                                class="mb-4 aspect-3/2 rounded-xl"
                                            />
                                        @endif

                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold text-zinc-900">{{ $hall->name }}</h3>
                                            <p class="mt-1.5 text-sm/6 text-zinc-600">{{ $hall->description }}</p>
                                        </div>

                                        <span @class([
                                            'mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                                            'border-brand-600 bg-brand-600 text-white' => $hall_id === $hall->id,
                                            'border-zinc-300' => $hall_id !== $hall->id,
                                        ])>
                                            @if ($hall_id === $hall->id)
                                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                                                </svg>
                                            @endif
                                        </span>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2 border-t border-zinc-200 pt-4">
                                        <span class="rounded-full bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ __('Rent: ₱:price / :hours hours', ['price' => number_format($hall->rent_price), 'hours' => \App\Models\Hall::HOURS_PER_BLOCK]) }}
                                        </span>
                                        <span class="rounded-full bg-coral-500 px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ __('Skirting: ₱:price', ['price' => number_format($hall->skirting_price)]) }}
                                        </span>
                                        <span class="rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600">
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
                <div class="min-w-0 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 lg:sticky lg:top-24 lg:max-h-[calc(100dvh-7.5rem)] lg:overflow-y-auto">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('Booking details') }}</h2>

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
                        <flux:input
                            wire:model.live="booking_date"
                            :label="__('Select date')"
                            type="date"
                            :min="now()->toDateString()"
                            required
                        />

                        <div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select wire:model.live="start_hour" :label="__('Start time')" :placeholder="__('Select start time')">
                                    @foreach ($this->startHours as $hour => $label)
                                        <flux:select.option wire:key="start-{{ $hour }}" :value="$hour">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:select
                                    wire:model.live="end_hour"
                                    :label="__('End time')"
                                    :placeholder="$start_hour === null ? __('Pick a start time first') : __('Select end time')"
                                    :disabled="$start_hour === null"
                                >
                                    @foreach ($this->endHours as $hour => $label)
                                        <flux:select.option wire:key="end-{{ $hour }}" :value="$hour">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <p class="mt-2 text-xs text-zinc-500">
                                {{ __('Open 7:00 AM to 10:00 PM. Halls are rented in blocks of 4 hours.') }}
                            </p>
                        </div>

                        <flux:switch
                            wire:model.live="include_skirting"
                            :label="__('Include skirting')"
                            :description="__('One-time skirting and setup fee.')"
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
                            <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5">
                                <h3 class="text-sm font-semibold text-zinc-900">{{ __('Price summary') }}</h3>

                                <dl class="mt-4 space-y-2.5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-zinc-600">
                                            {{ __('Rent (₱:rate × :blocks)', [
                                                'rate' => number_format($this->hall->rent_price),
                                                'blocks' => trans_choice('{1} :count block|[2,*] :count blocks', $this->quote['blocks'], ['count' => $this->quote['blocks']]),
                                            ]) }}
                                        </dt>
                                        <dd class="font-medium text-zinc-900">₱{{ number_format($this->quote['rent_total']) }}</dd>
                                    </div>

                                    @if ($this->quote['skirting_total'] > 0)
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-zinc-600">{{ __('Skirting and setup') }}</dt>
                                            <dd class="font-medium text-zinc-900">₱{{ number_format($this->quote['skirting_total']) }}</dd>
                                        </div>
                                    @endif

                                    <div class="flex justify-between gap-4 border-t border-brand-200 pt-2.5">
                                        <dt class="font-semibold text-zinc-900">{{ __('Total') }}</dt>
                                        <dd class="font-semibold text-zinc-900">₱{{ number_format($this->quote['total']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <dt class="font-semibold text-brand-700">{{ __('Downpayment (50%)') }}</dt>
                                        <dd class="font-bold text-brand-700">₱{{ number_format($this->quote['downpayment']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <dt class="text-zinc-600">{{ __('Balance on arrival') }}</dt>
                                        <dd class="font-medium text-zinc-900">₱{{ number_format($this->quote['balance']) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endif

                        <button
                            type="submit"
                            class="w-full rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
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
                class="fixed inset-0 z-60 flex items-end justify-center bg-zinc-900/60 p-4 backdrop-blur-sm sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="payment-title"
            >
                <div class="max-h-full w-full max-w-md overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <h2 id="payment-title" class="text-xl font-bold text-zinc-900">{{ __('GCash payment') }}</h2>

                        <button
                            type="button"
                            wire:click="cancelPayment"
                            class="-me-2 -mt-1 flex size-9 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900"
                        >
                            <span class="sr-only">{{ __('Close') }}</span>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <p class="mt-4 text-sm text-zinc-600">{{ __('Send this amount to hold your date:') }}</p>

                    <p class="mt-1 text-4xl font-bold tracking-tight text-brand-700">₱{{ number_format($this->quote['downpayment']) }}</p>

                    <dl class="mt-6 space-y-2.5 rounded-2xl bg-zinc-50 p-5 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Merchant') }}</dt>
                            <dd class="font-medium text-zinc-900">{{ config('app.name') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('GCash number') }}</dt>
                            <dd class="font-medium text-zinc-900">{{ config('resort.gcash.number') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Account name') }}</dt>
                            <dd class="font-medium text-zinc-900">{{ config('resort.gcash.account_name') }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-sm text-zinc-600">
                        {{ __('Send the exact amount, then keep a screenshot of your receipt — we will ask for it if we cannot match your payment.') }}
                    </p>

                    <button
                        type="button"
                        wire:click="confirmPayment"
                        class="mt-6 w-full rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
                        wire:loading.attr="disabled"
                        wire:target="confirmPayment"
                    >
                        <span wire:loading.remove wire:target="confirmPayment">{{ __('I have sent the payment') }}</span>
                        <span wire:loading wire:target="confirmPayment">{{ __('Saving your booking…') }}</span>
                    </button>

                    <button
                        type="button"
                        wire:click="cancelPayment"
                        class="mt-3 w-full rounded-xl px-6 py-3 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100"
                    >
                        {{ __('Go back') }}
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
