<?php

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomRate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::marketing')]
#[Title('Book a Room')]
class extends Component {
    public ?int $room_id = null;

    public string $checkin_date = '';

    public ?int $entry_hour = null;

    public ?int $rate_id = null;

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
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'checkin_date' => ['required', 'date', 'after_or_equal:today'],
            'entry_hour' => ['required', 'integer', 'min:'.Room::ENTRY_OPENS_AT, 'max:'.Room::ENTRY_CLOSES_AT],
            'rate_id' => [
                'required',
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
            'checkin_date.after_or_equal' => __('Pick a check-in date from today onwards.'),
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
        return Room::query()->active()->with('rates')->get();
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
     * The duration the guest has selected, if any.
     */
    #[Computed]
    public function rate(): ?RoomRate
    {
        return $this->room && $this->rate_id ? $this->room->rates->firstWhere('id', $this->rate_id) : null;
    }

    /**
     * The live price breakdown, once a room and duration are chosen.
     *
     * @return array{total: int, amount_paid: int, balance: int}|null
     */
    #[Computed]
    public function quote(): ?array
    {
        return $this->room && $this->rate ? $this->room->quote($this->rate, $this->payingInFull()) : null;
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
     * Drop a duration that belongs to a different room.
     */
    public function selectRoom(int $roomId): void
    {
        $this->room_id = $roomId;
        $this->rate_id = null;
        $this->resetValidation('room_id');
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
        $this->assertRoomIsAvailable();

        $quote = $this->room->quote($this->rate, $this->payingInFull());
        $startsAt = $this->startsAt();

        $booking = RoomBooking::create([
            'reference' => RoomBooking::generateReference(),
            'room_id' => $this->room_id,
            'user_id' => Auth::id(),
            'guest_name' => $this->guest_name,
            'guest_phone' => $this->guest_phone,
            'guest_email' => $this->guest_email,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours($this->rate->hours),
            'hours' => $this->rate->hours,
            'pay_in_full' => $this->payingInFull(),
            ...$quote,
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
        $this->reset(['room_id', 'checkin_date', 'entry_hour', 'rate_id', 'payment_option', 'reference', 'showPayment']);
        $this->mount();
    }

    /**
     * The start of the requested stay, or null while the date or time is missing.
     */
    protected function startsAt(): ?Carbon
    {
        if ($this->checkin_date === '' || $this->entry_hour === null) {
            return null;
        }

        return Carbon::parse($this->checkin_date)->setTime($this->entry_hour, 0);
    }

    /**
     * Reject the stay if a pending or confirmed booking already overlaps it.
     */
    protected function assertRoomIsAvailable(): void
    {
        $startsAt = $this->startsAt();
        $endsAt = $startsAt->copy()->addHours($this->rate->hours);

        $clashes = RoomBooking::query()
            ->blocking()
            ->where('room_id', $this->room_id)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($clashes) {
            $this->showPayment = false;

            throw ValidationException::withMessages([
                'checkin_date' => __('That room is taken for part of this stay. Please pick another time, date, or room.'),
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
                        {{ __('We are verifying your payment. You will get a confirmation once it clears — usually within 24 hours.') }}
                    </p>

                    @php $booking = $this->booking; @endphp

                    <div class="mt-6 rounded-2xl border border-brand-200 bg-brand-50/60 p-5">
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

                    <dl class="mt-8 divide-y divide-zinc-200 border-y border-zinc-200 text-sm">
                        @php
                            $rows = [
                                __('Reference') => $booking->reference,
                                __('Room') => $booking->room->name,
                                __('Check-in') => $booking->starts_at->format('F j, Y \a\t g:i A'),
                                __('Check-out') => $booking->ends_at->format('F j, Y \a\t g:i A'),
                                __('Duration') => trans_choice('{1} :count hour|[2,*] :count hours', $booking->hours, ['count' => $booking->hours]),
                                __('Name') => $booking->guest_name,
                                __('Phone') => $booking->guest_phone,
                                __('Email') => $booking->guest_email,
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
                            <dt class="text-zinc-500">
                                {{ $booking->pay_in_full ? __('Paid in full') : __('Downpayment sent') }}
                            </dt>
                            <dd class="text-right font-semibold text-brand-700">₱{{ number_format($booking->amount_paid) }}</dd>
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
                            {{ __('Book another room') }}
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
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">{{ __('Rooms') }}</p>

                <h1 class="mt-3 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl">
                    {{ __('Book a room') }}
                </h1>

                <p class="mx-auto mt-5 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                    {{ __('Pick a room, choose your check-in time and how long you are staying, then pay half now or the whole thing up front.') }}
                </p>
            </div>
        </section>

        <section class="bg-zinc-50 pb-20 pt-10 lg:pb-28">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:items-start lg:gap-8 lg:px-8">
                {{-- Room picker --}}
                <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('Select a room') }}</h2>

                    @if ($this->rooms->isEmpty())
                        <p class="mt-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
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
                                        'w-full rounded-2xl border p-5 text-left transition',
                                        'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' => $room_id === $room->id,
                                        'border-zinc-200 bg-white hover:border-brand-300 hover:bg-zinc-50' => $room_id !== $room->id,
                                    ])
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold text-zinc-900">{{ $room->name }}</h3>
                                            <p class="mt-1.5 text-sm/6 text-zinc-600">{{ $room->description }}</p>
                                        </div>

                                        <span @class([
                                            'mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                                            'border-brand-600 bg-brand-600 text-white' => $room_id === $room->id,
                                            'border-zinc-300' => $room_id !== $room->id,
                                        ])>
                                            @if ($room_id === $room->id)
                                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                                                </svg>
                                            @endif
                                        </span>
                                    </div>

                                    @if ($room->rates->isNotEmpty())
                                        <div class="mt-4 flex flex-wrap gap-2 border-t border-zinc-200 pt-4">
                                            @foreach ($room->rates as $rate)
                                                <span wire:key="rate-badge-{{ $rate->id }}" class="rounded-full bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">
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
                <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('Booking details') }}</h2>

                    <form wire:submit="proceedToPayment" class="mt-6 space-y-6">
                        <flux:input
                            wire:model.live="checkin_date"
                            :label="__('Check-in date')"
                            type="date"
                            :min="now()->toDateString()"
                            required
                        />

                        <flux:select wire:model.live="entry_hour" :label="__('Time of entry')" :placeholder="__('Select entry time')">
                            @foreach ($this->entryHours as $hour => $label)
                                <flux:select.option wire:key="entry-{{ $hour }}" :value="$hour">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select
                            wire:model.live="rate_id"
                            :label="__('Duration')"
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

                        <flux:radio.group wire:model.live="payment_option" :label="__('Payment option')" variant="segmented">
                            <flux:radio value="downpayment" :label="__('Downpayment (50%)')" />
                            <flux:radio value="full" :label="__('Pay in full')" />
                        </flux:radio.group>

                        @if ($this->arriveBy)
                            <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-4 text-sm text-brand-800">
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
                            <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5">
                                <h3 class="text-sm font-semibold text-zinc-900">{{ __('Price summary') }}</h3>

                                <dl class="mt-4 space-y-2.5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-zinc-600">{{ __('Room rate (:duration)', ['duration' => $this->rate->label()]) }}</dt>
                                        <dd class="font-medium text-zinc-900">₱{{ number_format($this->quote['total']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4 border-t border-brand-200 pt-2.5">
                                        <dt class="font-semibold text-zinc-900">{{ __('Total') }}</dt>
                                        <dd class="font-semibold text-zinc-900">₱{{ number_format($this->quote['total']) }}</dd>
                                    </div>

                                    <div class="flex justify-between gap-4">
                                        <dt class="font-semibold text-brand-700">
                                            {{ $this->payingInFull() ? __('Paying now (100%)') : __('Paying now (50%)') }}
                                        </dt>
                                        <dd class="font-bold text-brand-700">₱{{ number_format($this->quote['amount_paid']) }}</dd>
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
                class="fixed inset-0 z-[60] flex items-end justify-center bg-zinc-900/60 p-4 backdrop-blur-sm sm:items-center"
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

                    <p class="mt-4 text-sm text-zinc-600">
                        {{ $this->payingInFull() ? __('Send the full amount to hold your room:') : __('Send this amount to hold your room:') }}
                    </p>

                    <p class="mt-1 text-4xl font-bold tracking-tight text-brand-700">₱{{ number_format($this->quote['amount_paid']) }}</p>

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
