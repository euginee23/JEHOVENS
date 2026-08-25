@php
    $heroSlides = [
        ['file' => 'hero-slider-1.jpg', 'width' => 720, 'height' => 1200, 'alt' => __('The main pool and cottages at Jehoven\'s Garden Resort')],
        ['file' => 'hero-slider-2.jpg', 'width' => 995, 'height' => 1666, 'alt' => __('The swimming pool seen from the poolside')],
        ['file' => 'hero-slider-3.jpg', 'width' => 720, 'height' => 1201, 'alt' => __('The pool area dressed up for a celebration')],
        ['file' => 'hero-slider-4.jpg', 'width' => 720, 'height' => 1201, 'alt' => __('An event set up under the trees with buntings and tents')],
        ['file' => 'hero-slider-5.jpg', 'width' => 480, 'height' => 804, 'alt' => __('Garden greenery around the pool')],
    ];

    $amenities = [
        __('Function Halls (Aircon & Non-Aircon)'),
        __('Catering Services for Events'),
        __('Flexible Room Accommodations'),
        __('Adult & Kids Swimming Pools'),
        __('Easy Reservation Process'),
        __('Affordable Packages'),
        __('Event Decorations (Optional)'),
        __('Upcoming QR Food Ordering'),
    ];
@endphp

<x-layouts::marketing
    :description="__('Jehoven\'s Garden Resort offers function halls, catering, flexible room stays, and adult & kids pools — reserve any of them with a simple 50% down payment.')"
>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-white">
        <x-marketing.glow />

        <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-14 sm:px-6 lg:grid lg:grid-cols-[1.1fr_1fr] lg:items-center lg:gap-16 lg:pb-24 lg:pt-20 lg:px-8">
            <div class="max-w-2xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wider text-brand-700">
                    <span class="size-1.5 rounded-full bg-brand-500"></span>
                    {{ __('Now accepting reservations') }}
                </span>

                <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl lg:text-6xl">
                    {{ __('Let\'s relax and unwind at') }}
                    <span class="text-gradient">{{ __('Jehoven\'s Garden Resort') }}</span>
                </h1>

                <p class="mt-6 max-w-xl text-lg/8 text-pretty text-zinc-600">
                    {{ __('A perfect venue for relaxation and celebrations — function halls, catering, comfortable rooms, and refreshing pools for both adults and kids. Reserve any of them with a simple 50% down payment.') }}
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a
                        href="{{ auth()->check() ? route('dashboard') : route('register') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                    >
                        {{ __('Book your stay') }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>

                    <a
                        href="#services"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-300 bg-white px-6 py-3.5 text-sm font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50"
                    >
                        {{ __('Explore the resort') }}
                    </a>
                </div>

                <p class="mt-6 text-sm text-zinc-500">
                    {{ __('Only 50% down payment to hold your date — settle the balance on arrival.') }}
                </p>
            </div>

            {{-- Crossfading photo panel. Frame 1 renders visible so it is up before Alpine boots. --}}
            <div
                x-data="{ current: 0, total: {{ count($heroSlides) }} }"
                x-init="setInterval(() => current = (current + 1) % total, 5000)"
                class="relative mt-14 lg:mt-0"
            >
                <div class="relative aspect-4/5 overflow-hidden rounded-3xl bg-zinc-100 shadow-2xl shadow-brand-900/20 ring-1 ring-black/5 sm:aspect-3/4 lg:aspect-4/5">
                    @foreach ($heroSlides as $index => $slide)
                        <img
                            src="{{ asset('images/resort/'.$slide['file']) }}"
                            alt="{{ $slide['alt'] }}"
                            width="{{ $slide['width'] }}"
                            height="{{ $slide['height'] }}"
                            @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                            decoding="async"
                            x-bind:class="current === {{ $index }} ? 'opacity-100' : 'opacity-0'"
                            @class([
                                'absolute inset-0 size-full object-cover transition-opacity duration-1000 motion-reduce:transition-none',
                                'opacity-100' => $loop->first,
                                'opacity-0' => ! $loop->first,
                            ])
                        />
                    @endforeach

                    <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-32 bg-linear-to-t from-black/40 to-transparent"></div>

                    <div class="absolute bottom-5 left-1/2 flex -translate-x-1/2 gap-2">
                        @foreach ($heroSlides as $index => $slide)
                            <button
                                type="button"
                                x-on:click="current = {{ $index }}"
                                x-bind:class="current === {{ $index }} ? 'w-6 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80'"
                                class="h-1.5 rounded-full transition-all duration-300"
                            >
                                <span class="sr-only">{{ __('Show photo :number', ['number' => $index + 1]) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div aria-hidden="true" class="absolute -bottom-6 -left-6 -z-10 size-40 rounded-3xl bg-coral-400/20 blur-2xl"></div>
            </div>
        </div>
    </section>

    {{-- Trust strip --}}
    <section class="border-y border-zinc-200 bg-zinc-50">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-12 sm:px-6 lg:grid-cols-4 lg:px-8">
            <x-marketing.stat value="50%" :label="__('Down payment to reserve')" />
            <x-marketing.stat value="2" :label="__('Halls — aircon & non-aircon')" />
            <x-marketing.stat value="2" :label="__('Pools — adults & kids')" />
            <x-marketing.stat value="24/7" :label="__('Flexible room hours')" />
        </div>
    </section>

    {{-- Our services --}}
    <section id="services" class="scroll-mt-20 bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-marketing.section-heading
                :eyebrow="__('Our services')"
                :title="__('Everything you need in one place')"
                :description="__('Jehoven\'s Garden Resort is built for both quiet weekends and big celebrations — and every booking starts the same simple way.')"
            />

            <div class="mt-14 grid gap-6 lg:grid-cols-3">
                {{-- `lg:aspect-auto` + an absolutely-filled image lets the two card rows
                     drive this column's height instead of the photo's intrinsic size. --}}
                <div class="relative aspect-4/5 overflow-hidden rounded-3xl bg-zinc-100 sm:aspect-3/2 lg:row-span-2 lg:aspect-auto">
                    <img
                        src="{{ asset('images/resort/hero-slider-1.jpg') }}"
                        alt="{{ __('Grounds at Jehoven\'s Garden Resort') }}"
                        width="720"
                        height="1200"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover"
                    />
                    <div aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-brand-950/70 via-brand-950/10 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-7">
                        <p class="text-sm font-semibold uppercase tracking-wider text-brand-200">{{ __('The resort') }}</p>
                        <p class="mt-2 text-xl font-semibold text-white text-balance">
                            {{ __('Open grounds, cool water, and space to gather.') }}
                        </p>
                    </div>
                </div>

                <x-marketing.feature-card :title="__('Comfortable Rooms')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 4l9 6.5M5.25 9.5V19a1 1 0 0 0 1 1h11.5a1 1 0 0 0 1-1V9.5" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Enjoy flexible room stays with affordable rates for short or extended hours.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Catering Services')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v7a2.5 2.5 0 0 0 5 0V4M6.5 11v9M20 4c-1.7 1-2.5 3-2.5 5.5 0 1.6.5 2.5 2.5 2.5V4Zm0 8v8" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Delicious food packages available for birthdays and special events.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Easy Reservation')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3v2.5M16.5 3v2.5M3.5 9h17M4.5 6h15a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm5.5 8 1.5 1.5 3.5-3.5" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Book your stay or event easily with a simple 50% down payment process.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Event Facilities')">
                    <x-slot:icon>
                        <svg class="size-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M5 20V9l7-5 7 5v11M10 20v-5h4v5" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Spacious function halls perfect for gatherings, meetings, and celebrations.') }}
                </x-marketing.feature-card>
            </div>
        </div>
    </section>

    {{-- What you can book --}}
    <section class="border-t border-zinc-200 bg-zinc-50 py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-marketing.section-heading
                :eyebrow="__('What you can book')"
                :title="__('Pick the space, we\'ll handle the rest')"
                :description="__('Reserve a hall, a room, or a full catering package. Every booking is confirmed with a 50% down payment.')"
            />

            <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <x-marketing.offer-card
                    id="function-hall"
                    :title="__('Function Hall')"
                    :eyebrow="__('Events')"
                    :image="asset('images/resort/hero-slider-4.jpg')"
                    :items="[
                        __('Aircon and non-aircon halls'),
                        __('Seating for gatherings and meetings'),
                        __('Optional event decorations'),
                    ]"
                    :cta="__('Reserve a hall')"
                    :href="auth()->check() ? route('dashboard') : route('register')"
                >
                    {{ __('Spacious halls for birthdays, reunions, meetings, and celebrations of every size.') }}
                </x-marketing.offer-card>

                <x-marketing.offer-card
                    id="rooms"
                    :title="__('Rooms')"
                    :eyebrow="__('Stay')"
                    :image="asset('images/resort/hero-slider-5.jpg')"
                    :items="[
                        __('Short-hour or overnight stays'),
                        __('Affordable, flexible rates'),
                        __('Pool access for adults and kids'),
                    ]"
                    :cta="__('Reserve a room')"
                    :href="auth()->check() ? route('dashboard') : route('register')"
                >
                    {{ __('Comfortable accommodations you can book by the hour or for the whole night.') }}
                </x-marketing.offer-card>

                <x-marketing.offer-card
                    id="catering"
                    :title="__('Catering')"
                    :eyebrow="__('Food')"
                    :image="asset('images/resort/hero-slider-3.jpg')"
                    :items="[
                        __('Packages for birthdays and events'),
                        __('Menus built around your guest count'),
                        __('QR code food ordering coming soon'),
                    ]"
                    :cta="__('Order catering')"
                    :href="auth()->check() ? route('dashboard') : route('register')"
                >
                    {{ __('Food packages prepared on-site so your guests never have to leave the party.') }}
                </x-marketing.offer-card>
            </div>
        </div>
    </section>

    {{-- Discover --}}
    <section id="about" class="scroll-mt-20 bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                <div class="relative">
                    <div class="overflow-hidden rounded-3xl bg-zinc-100 shadow-xl shadow-brand-900/10 ring-1 ring-black/5">
                        <img
                            src="{{ asset('images/resort/hero-slider-2.jpg') }}"
                            alt="{{ __('Swimming pool at Jehoven\'s Garden Resort') }}"
                            width="995"
                            height="1666"
                            loading="lazy"
                            decoding="async"
                            class="aspect-4/3 size-full object-cover lg:aspect-square"
                        />
                    </div>
                    <div aria-hidden="true" class="absolute -bottom-8 -right-8 -z-10 size-48 rounded-3xl bg-brand-200/50 blur-2xl"></div>
                </div>

                <div>
                    <x-marketing.section-heading
                        align="left"
                        :eyebrow="__('About us')"
                        :title="__('Discover Jehoven\'s Garden Resort')"
                        :description="__('A relaxing place for both leisure and events, with easy reservation requiring only a 50% down payment. Guests enjoy function halls, catering services, comfortable rooms, and refreshing pool facilities.')"
                    />

                    <p class="mt-4 max-w-2xl text-lg/8 text-pretty text-zinc-600">
                        {{ __('Perfect for birthdays, gatherings, and outings — and soon, a QR code food ordering system for a faster, more convenient dining experience.') }}
                    </p>

                    <ul class="mt-8 grid gap-x-8 gap-y-3.5 sm:grid-cols-2">
                        @foreach ($amenities as $amenity)
                            <li class="flex gap-2.5 text-sm/6 text-zinc-700">
                                <svg class="mt-1 size-4.5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                                </svg>
                                {{ $amenity }}
                            </li>
                        @endforeach
                    </ul>

                    <a
                        href="{{ auth()->check() ? route('dashboard') : route('register') }}"
                        class="mt-10 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                    >
                        {{ __('Book now') }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="relative isolate overflow-hidden bg-brand-900">
        <x-marketing.glow variant="cta" />

        <div class="relative mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8 lg:py-24">
            <h2 class="text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">
                {{ __('Plan your perfect stay at Jehoven\'s Garden Resort') }}
            </h2>

            <p class="mx-auto mt-5 max-w-2xl text-lg/8 text-pretty text-brand-100">
                {{ __('Book your cottages, rooms, and event spaces with our simple reservation system and enjoy a relaxing getaway.') }}
            </p>

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                <a
                    href="{{ auth()->check() ? route('dashboard') : route('register') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-brand-800 shadow-sm transition hover:bg-brand-50"
                >
                    {{ __('Book now') }}
                    <span aria-hidden="true">&rarr;</span>
                </a>

                @guest
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center gap-2 rounded-xl border border-white/30 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-white/10"
                    >
                        {{ __('I already have an account') }}
                    </a>
                @endguest
            </div>
        </div>
    </section>
</x-layouts::marketing>
