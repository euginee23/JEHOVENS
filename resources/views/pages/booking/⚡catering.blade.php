<?php

use App\Enums\BookingStatus;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::marketing')]
#[Title('Order Catering')]
class extends Component {
    public ?int $package_id = null;

    public string $event_date = '';

    public ?int $guests = null;

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
     * The reference of the order just created, which switches the page to the
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
     * Validation rules for the order form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:catering_packages,id'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'guests' => [
                'required',
                'integer',
                'max:'.CateringPackage::MAX_GUESTS,
                function (string $attribute, mixed $value, callable $fail) {
                    $minimum = $this->package?->minimum_guests ?? 1;

                    if ($value < $minimum) {
                        $fail(__('This package is for a minimum of :count guests.', ['count' => $minimum]));
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
            'package_id.required' => __('Choose a catering package first.'),
            'event_date.after_or_equal' => __('Pick an event date from today onwards.'),
            'guests.required' => __('Tell us how many guests you are expecting.'),
            'guest_phone.regex' => __('Enter an 11-digit mobile number starting with 09, e.g. 09123456789.'),
        ];
    }

    /**
     * The packages a guest can currently order.
     *
     * @return Collection<int, CateringPackage>
     */
    #[Computed]
    public function packages(): Collection
    {
        return CateringPackage::query()->active()->with('photos')->get();
    }

    /**
     * The package the guest has selected, if any.
     */
    #[Computed]
    public function package(): ?CateringPackage
    {
        return $this->package_id ? $this->packages->firstWhere('id', $this->package_id) : null;
    }

    /**
     * The live price breakdown, once a package and head count are chosen.
     *
     * @return array{catering_total: int, skirting_total: int, total: int, downpayment: int, balance: int}|null
     */
    #[Computed]
    public function quote(): ?array
    {
        if (! $this->package || ! $this->guests || $this->guests < 1) {
            return null;
        }

        return $this->package->quote($this->guests, $this->include_skirting);
    }

    /**
     * The order created by this session, once payment has been acknowledged.
     */
    #[Computed]
    public function order(): ?CateringOrder
    {
        return $this->reference ? CateringOrder::with('package')->where('reference', $this->reference)->first() : null;
    }

    /**
     * Select a package, defaulting the head count to its minimum.
     */
    public function selectPackage(int $packageId): void
    {
        $this->package_id = $packageId;
        $this->guests ??= $this->package?->minimum_guests;
        $this->resetValidation(['package_id', 'guests']);
    }

    /**
     * Validate the form and open the GCash panel.
     */
    public function proceedToPayment(): void
    {
        $this->validate();

        $this->showPayment = true;
    }

    /**
     * Close the GCash panel without ordering.
     */
    public function cancelPayment(): void
    {
        $this->showPayment = false;
    }

    /**
     * Record the order as pending once the guest says the downpayment is sent.
     */
    public function confirmPayment(): void
    {
        $validated = $this->validate();
        // The form field is `package_id`; the column it maps to is `catering_package_id`.
        unset($validated['package_id']);

        $package = $this->package;
        $quote = $package->quote($this->guests, $this->include_skirting);

        $order = CateringOrder::create([
            ...$validated,
            'catering_package_id' => $package->id,
            'reference' => CateringOrder::generateReference(),
            'user_id' => Auth::id(),
            'price_per_head' => $package->price_per_head,
            ...$quote,
            'status' => BookingStatus::Pending,
        ]);

        $this->showPayment = false;
        $this->reference = $order->reference;
    }

    /**
     * Start over on a fresh order form.
     */
    public function orderAnother(): void
    {
        $this->reset(['package_id', 'event_date', 'guests', 'reference', 'showPayment']);
        $this->include_skirting = true;
        $this->mount();
    }
}; ?>

<div>
    @if ($this->order)
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

                    <h1 class="mt-6 text-3xl font-bold tracking-tight text-zinc-900">{{ __('Order received') }}</h1>

                    <p class="mt-3 text-zinc-600">
                        {{ __('We are verifying your downpayment. You will get a confirmation once it clears — usually within 24 hours.') }}
                    </p>

                    @php $order = $this->order; @endphp

                    <dl class="mt-8 divide-y divide-zinc-200 border-y border-zinc-200 text-sm">
                        @php
                            $rows = [
                                __('Reference') => $order->reference,
                                __('Package') => $order->package->name,
                                __('Event date') => $order->event_date->format('F j, Y'),
                                __('Guests') => number_format($order->guests),
                                __('Name') => $order->guest_name,
                                __('Phone') => $order->guest_phone,
                                __('Email') => $order->guest_email,
                                __('Skirting') => $order->include_skirting ? __('Included') : __('Not included'),
                            ];
                        @endphp

                        @foreach ($rows as $label => $value)
                            <div class="flex justify-between gap-6 py-3" wire:key="row-{{ $loop->index }}">
                                <dt class="text-zinc-500">{{ $label }}</dt>
                                <dd class="text-right font-medium text-zinc-900">{{ $value }}</dd>
                            </div>
                        @endforeach

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-zinc-500">
                                {{ __('Catering (₱:rate × :guests)', ['rate' => number_format($order->price_per_head), 'guests' => number_format($order->guests)]) }}
                            </dt>
                            <dd class="text-right font-medium text-zinc-900">₱{{ number_format($order->catering_total) }}</dd>
                        </div>

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-zinc-500">{{ __('Total') }}</dt>
                            <dd class="text-right font-medium text-zinc-900">₱{{ number_format($order->total) }}</dd>
                        </div>

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-zinc-500">{{ __('Downpayment sent') }}</dt>
                            <dd class="text-right font-semibold text-brand-700">₱{{ number_format($order->downpayment) }}</dd>
                        </div>

                        <div class="flex justify-between gap-6 py-3">
                            <dt class="text-zinc-500">{{ __('Balance on the day') }}</dt>
                            <dd class="text-right font-medium text-zinc-900">₱{{ number_format($order->balance) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="orderAnother"
                            class="rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                        >
                            {{ __('Place another order') }}
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
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">{{ __('Catering') }}</p>

                <h1 class="mt-3 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl">
                    {{ __('Order catering services') }}
                </h1>

                <p class="mx-auto mt-5 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                    {{ __('Pick a package, tell us your event date and head count, then hold it with a 50% downpayment.') }}
                </p>
            </div>
        </section>

        <section class="bg-zinc-50 pb-20 pt-10 lg:pb-28">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.15fr_1fr] lg:items-start lg:gap-8 lg:px-8">
                {{-- Package picker --}}
                <div class="min-w-0 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('Select a catering package') }}</h2>

                    @if ($this->packages->isEmpty())
                        <p class="mt-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                            {{ __('No catering packages are available right now. Please check back later.') }}
                        </p>
                    @else
                        <div class="mt-6 space-y-4" role="radiogroup" aria-label="{{ __('Catering packages') }}">
                            @foreach ($this->packages as $package)
                                <button
                                    type="button"
                                    wire:key="package-{{ $package->id }}"
                                    wire:click="selectPackage({{ $package->id }})"
                                    role="radio"
                                    aria-checked="{{ $package_id === $package->id ? 'true' : 'false' }}"
                                    @class([
                                        'w-full rounded-2xl border p-5 text-left transition',
                                        'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' => $package_id === $package->id,
                                        'border-zinc-200 bg-white hover:border-brand-300 hover:bg-zinc-50' => $package_id !== $package->id,
                                    ])
                                >
                                    @if ($package->photos->isNotEmpty())
                                        {{-- No dots: this card is a <button>, and nested
                                             interactive elements would both be invalid HTML
                                             and steal the click that selects the package. --}}
                                        <x-marketing.photo-slideshow
                                            :photos="$package->photoSlides()"
                                            :dots="false"
                                            class="mb-4 aspect-3/2 rounded-xl"
                                        />
                                    @endif

                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <h3 class="text-lg font-semibold text-zinc-900">{{ $package->name }}</h3>
                                            <p class="mt-1.5 text-sm/6 text-zinc-600">{{ $package->description }}</p>
                                        </div>

                                        <span @class([
                                            'mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                                            'border-brand-600 bg-brand-600 text-white' => $package_id === $package->id,
                                            'border-zinc-300' => $package_id !== $package->id,
                                        ])>
                                            @if ($package_id === $package->id)
                                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                                                </svg>
                                            @endif
                                        </span>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2 border-t border-zinc-200 pt-4">
                                        <span class="rounded-full bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ __('₱:price per head', ['price' => number_format($package->price_per_head)]) }}
                                        </span>
                                        <span class="rounded-full bg-coral-500 px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ __('Skirting: ₱:price', ['price' => number_format($package->skirting_price)]) }}
                                        </span>
                                        <span class="rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600">
                                            {{ __('Min. :count guests', ['count' => number_format($package->minimum_guests)]) }}
                                        </span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @error('package_id')
                        <p class="mt-4 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Order details --}}
                <div class="min-w-0 rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 lg:sticky lg:top-24 lg:max-h-[calc(100dvh-7.5rem)] lg:overflow-y-auto">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('Order details') }}</h2>

                    <x-booking.selection
                        class="mt-5"
                        :name="$this->package?->name"
                        :prompt="__('Pick a package from the list to get started.')"
                        :facts="$this->package ? [
                            __('₱:price per head', ['price' => number_format($this->package->price_per_head)]),
                            __('Skirting ₱:price', ['price' => number_format($this->package->skirting_price)]),
                            __('min. :count guests', ['count' => number_format($this->package->minimum_guests)]),
                        ] : []"
                    />
                    <form wire:submit="proceedToPayment" class="mt-6 space-y-6">
                        <flux:input
                            wire:model.live="event_date"
                            :label="__('Event date')"
                            type="date"
                            :min="now()->toDateString()"
                            required
                        />

                        <flux:input
                            wire:model.live.debounce.400ms="guests"
                            :label="__('Number of guests')"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            :max="\App\Models\CateringPackage::MAX_GUESTS"
                            :placeholder="__('e.g. 80')"
                            :description="$this->package ? __('This package is for a minimum of :count guests.', ['count' => number_format($this->package->minimum_guests)]) : null"
                            required
                        />

                        <flux:switch
                            wire:model.live="include_skirting"
                            :label="__('Include skirting')"
                            :description="$this->package
                                ? __('One-time skirting and setup fee (₱:price).', ['price' => number_format($this->package->skirting_price)])
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
                            <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5">
                                <h3 class="text-sm font-semibold text-zinc-900">{{ __('Price summary') }}</h3>

                                <dl class="mt-4 space-y-2.5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-zinc-600">
                                            {{ __('Catering (₱:rate × :guests)', [
                                                'rate' => number_format($this->package->price_per_head),
                                                'guests' => trans_choice('{1} :count guest|[2,*] :count guests', $guests, ['count' => number_format($guests)]),
                                            ]) }}
                                        </dt>
                                        <dd class="font-medium text-zinc-900">₱{{ number_format($this->quote['catering_total']) }}</dd>
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
                                        <dt class="text-zinc-600">{{ __('Balance on the day') }}</dt>
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
                            <span wire:loading wire:target="proceedToPayment">{{ __('Checking your order…') }}</span>
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
                        <span wire:loading wire:target="confirmPayment">{{ __('Saving your order…') }}</span>
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
