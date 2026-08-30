@php
    // Every hero shot is a portrait phone photo, so a wide viewport crops it hard. The
    // `position` is the band worth keeping in each one — the pool edge, the cottages, the
    // palm canopy — instead of the dead centre, which lands on a tree trunk.
    $heroSlides = [
        ['file' => 'resort/hero-slider-1.jpg', 'width' => 720, 'height' => 1200, 'position' => '50% 42%', 'alt' => __('The main pool and cottages at Jehoven\'s Garden Resort')],
        ['file' => 'resort/hero-slider-2.jpg', 'width' => 995, 'height' => 1666, 'position' => '50% 30%', 'alt' => __('The swimming pool seen from the poolside')],
        ['file' => 'resort/hero-slider-3.jpg', 'width' => 720, 'height' => 1201, 'position' => '50% 38%', 'alt' => __('The pool area dressed up for a celebration')],
        ['file' => 'resort/hero-slider-4.jpg', 'width' => 720, 'height' => 1201, 'position' => '50% 55%', 'alt' => __('An event set up under the trees with buntings and tents')],
        ['file' => 'resort/hero-slider-5.jpg', 'width' => 480, 'height' => 804, 'position' => '50% 45%', 'alt' => __('Garden greenery around the pool')],
    ];

    $hallPhotos = [
        ['file' => 'function-hall/function-hall-1.jpg', 'width' => 1600, 'height' => 1200, 'alt' => __('The function hall with draped ceiling, stage backdrop, and stacked chairs')],
        ['file' => 'resort/hero-slider-4.jpg', 'width' => 720, 'height' => 1201, 'alt' => __('An event set up under the trees with buntings and tents')],
    ];

    $roomPhotos = [
        ['file' => 'rooms/room-1.jpg', 'width' => 1856, 'height' => 1870, 'alt' => __('Air-conditioned room with a double bed and wicker frame')],
        ['file' => 'rooms/room-2.jpg', 'width' => 1536, 'height' => 2048, 'alt' => __('Room with a double bed, air-conditioning, and shelving')],
        ['file' => 'rooms/room-3.jpg', 'width' => 1536, 'height' => 2048, 'alt' => __('Room with a wicker sofa, side table, and a double bed')],
    ];

    $cateringPhotos = [
        ['file' => 'catering/catering-1.jpg', 'width' => 1536, 'height' => 2048, 'alt' => __('Buffet spread laid out for an event at the resort')],
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

    // The gallery reuses the photographs already shown elsewhere on the page; the first
    // entry runs tall so the grid reads as an editorial spread rather than a contact sheet.
    $gallery = [
        ['file' => 'resort/hero-slider-3.jpg', 'width' => 720, 'height' => 1201, 'alt' => __('The pool area dressed up for a celebration'), 'span' => true],
        ['file' => 'function-hall/function-hall-1.jpg', 'width' => 1600, 'height' => 1200, 'alt' => __('The function hall with draped ceiling and stage backdrop')],
        ['file' => 'rooms/room-1.jpg', 'width' => 1856, 'height' => 1870, 'alt' => __('Air-conditioned room with a double bed and wicker frame')],
        ['file' => 'catering/catering-1.jpg', 'width' => 1536, 'height' => 2048, 'alt' => __('Buffet spread laid out for an event at the resort')],
        ['file' => 'resort/hero-slider-5.jpg', 'width' => 480, 'height' => 804, 'alt' => __('Garden greenery around the pool')],
    ];
@endphp

<x-layouts::marketing
    transparent-nav
    :description="__('Jehoven\'s Garden Resort offers function halls, catering, flexible room stays, and adult & kids pools — reserve any of them with a simple 50% down payment.')"
>
    {{-- Hero.

         Two rows rather than one centred block: the copy takes whatever height is left
         over and the availability bar sits on the hero's own bottom edge, so the bar is
         always inside the photograph and inside the viewport however tall the hero is. --}}
    <section class="relative isolate grid min-h-svh grid-rows-[1fr_auto] overflow-hidden">
        <x-marketing.photo-slideshow
            :photos="$heroSlides"
            cover
            scrim="full"
            dots
            dots-align="end"
            eager
        />

        <div class="relative mx-auto flex w-full max-w-7xl items-center px-4 pb-10 pt-28 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="eyebrow text-gold-300">{{ __('Escape. Relax. Celebrate.') }}</p>

                <h1 class="mt-6 font-serif text-5xl/none font-medium text-balance text-white sm:text-6xl/none lg:text-7xl/none">
                    {{ __('Your Garden Escape') }}
                    <span class="block">{{ __('Awaits') }}</span>
                    <span class="mt-2 block font-serif text-4xl/none font-normal italic text-gold-200 sm:text-5xl/none lg:text-6xl/none">
                        {{ __('every day of the year') }}
                    </span>
                </h1>

                <p class="mt-8 max-w-lg text-base/8 text-pretty text-sand-100/80">
                    {{ __('Function halls, catering, comfortable rooms, and refreshing pools for adults and kids — all in one place, and all held with a simple 50% down payment.') }}
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <a
                        href="#services"
                        class="eyebrow bg-brand-800 px-8 py-4 text-[11px] text-white shadow-lg shadow-brand-950/30 transition hover:bg-brand-700"
                    >
                        {{ __('Explore the resort') }}
                    </a>

                    <a
                        href="#gallery"
                        class="eyebrow inline-flex items-center gap-3 border border-white/50 px-8 py-4 text-[11px] text-white transition hover:border-white hover:bg-white/10"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 6.5h17v11h-17v-11Zm2.5 8 3.5-3.5 3 3 2.5-2.5 3 3" />
                            <circle cx="9" cy="10" r="1.1" />
                        </svg>
                        {{ __('View the gallery') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- The generous bottom padding is what the slideshow dots sit in, so they clear
             the bar instead of hiding behind it. --}}
        <div class="relative mx-auto w-full max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <x-marketing.availability-bar class="mx-auto max-w-6xl" />
        </div>
    </section>

    {{-- Trust strip --}}
    <section class="bg-sand-50">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-y-10 px-4 py-16 sm:px-6 lg:grid-cols-4 lg:gap-0 lg:px-8 lg:py-20">
            <x-marketing.stat value="50%" :label="__('Down payment to reserve')" class="lg:border-e lg:border-sand-200" />
            <x-marketing.stat value="2" :label="__('Halls — aircon & non-aircon')" class="lg:border-e lg:border-sand-200" />
            <x-marketing.stat value="2" :label="__('Pools — adults & kids')" class="lg:border-e lg:border-sand-200" />
            <x-marketing.stat value="24/7" :label="__('Flexible room hours')" />
        </div>
    </section>

    {{-- Our services --}}
    <section class="bg-white py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div id="services" class="scroll-mt-28">
                <x-marketing.section-heading
                    :eyebrow="__('Our services')"
                    :title="__('Everything you need in one place')"
                    :description="__('Jehoven\'s Garden Resort is built for both quiet weekends and big celebrations — and every booking starts the same simple way.')"
                />
            </div>

            <div class="mt-16 grid gap-px bg-sand-200 lg:grid-cols-3">
                {{-- `lg:aspect-auto` + an absolutely-filled image lets the two card rows
                     drive this column's height instead of the photo's intrinsic size. --}}
                <div class="relative aspect-4/5 overflow-hidden bg-sand-100 sm:aspect-3/2 lg:row-span-2 lg:aspect-auto">
                    <img
                        src="{{ asset('images/resort/hero-slider-1.jpg') }}"
                        alt="{{ __('Grounds at Jehoven\'s Garden Resort') }}"
                        width="720"
                        height="1200"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover"
                    />
                    <div aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-brand-950/80 via-brand-950/15 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-8">
                        <p class="eyebrow text-gold-300">{{ __('The resort') }}</p>
                        <p class="mt-3 font-serif text-2xl text-balance text-white">
                            {{ __('Open grounds, cool water, and space to gather.') }}
                        </p>
                    </div>
                </div>

                <x-marketing.feature-card :title="__('Comfortable Rooms')">
                    <x-slot:icon>
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 4l9 6.5M5.25 9.5V19a1 1 0 0 0 1 1h11.5a1 1 0 0 0 1-1V9.5" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Enjoy flexible room stays with affordable rates for short or extended hours.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Catering Services')">
                    <x-slot:icon>
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v7a2.5 2.5 0 0 0 5 0V4M6.5 11v9M20 4c-1.7 1-2.5 3-2.5 5.5 0 1.6.5 2.5 2.5 2.5V4Zm0 8v8" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Delicious food packages available for birthdays and special events.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Easy Reservation')">
                    <x-slot:icon>
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3v2.5M16.5 3v2.5M3.5 9h17M4.5 6h15a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm5.5 8 1.5 1.5 3.5-3.5" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Book your stay or event easily with a simple 50% down payment process.') }}
                </x-marketing.feature-card>

                <x-marketing.feature-card :title="__('Event Facilities')">
                    <x-slot:icon>
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M5 20V9l7-5 7 5v11M10 20v-5h4v5" />
                        </svg>
                    </x-slot:icon>
                    {{ __('Spacious function halls perfect for gatherings, meetings, and celebrations.') }}
                </x-marketing.feature-card>
            </div>
        </div>
    </section>

    {{-- What you can book --}}
    <section class="bg-sand-50 py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div id="book" class="scroll-mt-28">
                <x-marketing.section-heading
                    :eyebrow="__('What you can book')"
                    :title="__('Pick the space, we\'ll handle the rest')"
                    :description="__('Reserve a hall, a room, or a full catering package. Every booking is confirmed with a 50% down payment.')"
                />
            </div>

            <div class="mt-16 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <x-marketing.offer-card
                    id="function-hall"
                    :title="__('Function Hall')"
                    :eyebrow="__('Events')"
                    :photos="$hallPhotos"
                    :items="[
                        __('Aircon and non-aircon halls'),
                        __('Seating for gatherings and meetings'),
                        __('Optional event decorations'),
                    ]"
                    :cta="__('Reserve a hall')"
                    :href="route('booking.function-hall')"
                >
                    {{ __('Spacious halls for birthdays, reunions, meetings, and celebrations of every size.') }}
                </x-marketing.offer-card>

                <x-marketing.offer-card
                    id="rooms"
                    :title="__('Rooms')"
                    :eyebrow="__('Stay')"
                    :photos="$roomPhotos"
                    :items="[
                        __('Short-hour or overnight stays'),
                        __('Affordable, flexible rates'),
                        __('Pool access for adults and kids'),
                    ]"
                    :cta="__('Reserve a room')"
                    :href="route('booking.rooms')"
                >
                    {{ __('Comfortable accommodations you can book by the hour or for the whole night.') }}
                </x-marketing.offer-card>

                <x-marketing.offer-card
                    id="catering"
                    :title="__('Catering')"
                    :eyebrow="__('Food')"
                    :photos="$cateringPhotos"
                    :items="[
                        __('Packages for birthdays and events'),
                        __('Menus built around your guest count'),
                        __('QR code food ordering coming soon'),
                    ]"
                    :cta="__('Order catering')"
                    :href="route('booking.catering')"
                >
                    {{ __('Food packages prepared on-site so your guests never have to leave the party.') }}
                </x-marketing.offer-card>
            </div>
        </div>
    </section>

    {{-- Discover --}}
    {{-- `overflow-hidden` clips the decorative offset frame behind the photo, which
         otherwise pushes the page wider than the viewport on small screens. --}}
    <section class="overflow-hidden bg-white py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div id="about" class="grid scroll-mt-28 items-center gap-16 lg:grid-cols-2 lg:gap-24">
                <div class="relative">
                    <div class="overflow-hidden bg-sand-100">
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
                    <div aria-hidden="true" class="pointer-events-none absolute -bottom-6 -right-6 -z-10 size-full border border-gold-400/60"></div>
                </div>

                <div>
                    <x-marketing.section-heading
                        align="left"
                        :eyebrow="__('About us')"
                        :title="__('Discover Jehoven\'s Garden Resort')"
                        :description="__('A relaxing place for both leisure and events, with easy reservation requiring only a 50% down payment. Guests enjoy function halls, catering services, comfortable rooms, and refreshing pool facilities.')"
                    />

                    <p class="mt-4 max-w-2xl text-base/7 text-pretty text-brand-800/70">
                        {{ __('Perfect for birthdays, gatherings, and outings — and soon, a QR code food ordering system for a faster, more convenient dining experience.') }}
                    </p>

                    <ul class="mt-10 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                        @foreach ($amenities as $amenity)
                            <li class="flex gap-3 text-sm/6 text-brand-800">
                                <svg class="mt-1 size-4 shrink-0 text-gold-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                                </svg>
                                {{ $amenity }}
                            </li>
                        @endforeach
                    </ul>

                    <a
                        href="{{ route('booking.function-hall') }}"
                        class="eyebrow mt-12 inline-block bg-brand-800 px-8 py-4 text-[11px] text-white transition hover:bg-brand-700"
                    >
                        {{ __('Book now') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Gallery --}}
    <section class="bg-sand-50 py-24 lg:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div id="gallery" class="scroll-mt-28">
                <x-marketing.section-heading
                    :eyebrow="__('Gallery')"
                    :title="__('A look around the grounds')"
                    :description="__('Poolside afternoons, halls dressed for a celebration, and rooms ready for the night.')"
                />
            </div>

            <div class="mt-16 grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach ($gallery as $photo)
                    <figure @class([
                        'group relative overflow-hidden bg-sand-100',
                        'col-span-2 row-span-2' => $photo['span'] ?? false,
                        'aspect-square' => ! ($photo['span'] ?? false),
                    ])>
                        <img
                            src="{{ asset('images/'.$photo['file']) }}"
                            alt="{{ $photo['alt'] }}"
                            width="{{ $photo['width'] }}"
                            height="{{ $photo['height'] }}"
                            loading="lazy"
                            decoding="async"
                            @class([
                                'size-full object-cover transition-transform duration-700 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100',
                                'aspect-square lg:aspect-auto' => $photo['span'] ?? false,
                            ])
                        />
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Closing CTA and contact --}}
    <section id="contact" class="relative isolate scroll-mt-28 overflow-hidden bg-brand-950">
        <img
            src="{{ asset('images/resort/hero-slider-4.jpg') }}"
            alt=""
            width="720"
            height="1201"
            loading="lazy"
            decoding="async"
            aria-hidden="true"
            class="absolute inset-0 -z-10 size-full object-cover opacity-15"
        />
        <x-marketing.glow variant="cta" class="-z-10" />

        <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8 lg:py-32">
            <div class="mx-auto max-w-3xl text-center">
                <x-marketing.section-heading
                    tone="dark"
                    :eyebrow="__('Reserve your date')"
                    :title="__('Plan your perfect stay at Jehoven\'s Garden Resort')"
                    :description="__('Book your rooms, halls, and catering with our simple reservation system and enjoy a relaxing getaway.')"
                />

                <div class="mt-12 flex flex-wrap items-center justify-center gap-4">
                    <a
                        href="#book"
                        class="eyebrow bg-gold-500 px-8 py-4 text-[11px] text-brand-950 transition hover:bg-gold-400"
                    >
                        {{ __('Book now') }}
                    </a>

                    <a
                        href="{{ route('booking.rooms') }}"
                        class="eyebrow border border-white/40 px-8 py-4 text-[11px] text-white transition hover:border-white hover:bg-white/10"
                    >
                        {{ __('Book a room') }}
                    </a>
                </div>
            </div>

            <dl class="mx-auto mt-20 grid max-w-4xl gap-10 border-t border-white/10 pt-14 text-center sm:grid-cols-3">
                <div>
                    <dt class="eyebrow text-gold-300">{{ __('Reserve with GCash') }}</dt>
                    <dd class="mt-3 font-serif text-2xl text-white">{{ config('resort.gcash.number') }}</dd>
                    <dd class="mt-1 text-sm text-sand-200/70">{{ config('resort.gcash.account_name') }}</dd>
                </div>

                <div>
                    <dt class="eyebrow text-gold-300">{{ __('Down payment') }}</dt>
                    <dd class="mt-3 font-serif text-2xl text-white">{{ __('50% to hold') }}</dd>
                    <dd class="mt-1 text-sm text-sand-200/70">{{ __('Balance settled on arrival') }}</dd>
                </div>

                <div>
                    <dt class="eyebrow text-gold-300">{{ __('Room entry') }}</dt>
                    <dd class="mt-3 font-serif text-2xl text-white">
                        {{ __(':from AM — :to PM', ['from' => \App\Models\Room::ENTRY_OPENS_AT, 'to' => \App\Models\Room::ENTRY_CLOSES_AT - 12]) }}
                    </dd>
                    <dd class="mt-1 text-sm text-sand-200/70">{{ __('Arrive 30 minutes early') }}</dd>
                </div>
            </dl>
        </div>
    </section>
</x-layouts::marketing>
