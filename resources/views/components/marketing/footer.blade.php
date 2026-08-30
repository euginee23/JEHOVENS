@php
    $explore = [
        ['label' => __('Function Hall'), 'href' => route('booking.function-hall')],
        ['label' => __('Rooms'), 'href' => route('booking.rooms')],
        ['label' => __('Catering'), 'href' => route('booking.catering')],
        ['label' => __('About the resort'), 'href' => route('home').'#about'],
        ['label' => __('Gallery'), 'href' => route('home').'#gallery'],
    ];
@endphp

<footer class="bg-brand-950 text-sand-100">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-12 md:grid-cols-2 lg:grid-cols-4 lg:gap-8">
            <div class="lg:col-span-2">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <x-marketing.logo-mark class="size-9 text-gold-400" />

                    <span>
                        <span class="block font-serif text-2xl/7 font-semibold tracking-wide text-white">
                            {{ config('app.name') }}
                        </span>
                        <span class="eyebrow mt-0.5 block text-[10px] text-gold-300/80">{{ __('Garden Resort & Events') }}</span>
                    </span>
                </a>

                <p class="mt-6 max-w-sm text-sm/7 text-sand-200/80">
                    {{ __('A relaxing place for leisure and celebrations — function halls, catering, comfortable rooms, and pools for adults and kids, all reservable with a 50% down payment.') }}
                </p>
            </div>

            <div>
                <h2 class="eyebrow text-gold-300">{{ __('Explore') }}</h2>
                <ul class="mt-6 space-y-3.5">
                    @foreach ($explore as $link)
                        <li>
                            <a href="{{ $link['href'] }}" class="text-sm text-sand-200/80 transition-colors hover:text-gold-300">
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="eyebrow text-gold-300">{{ __('Book with us') }}</h2>
                <ul class="mt-6 space-y-3.5">
                    <li>
                        <a href="{{ route('booking.function-hall') }}" class="text-sm text-sand-200/80 transition-colors hover:text-gold-300">
                            {{ __('Reserve a function hall') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('booking.rooms') }}" class="text-sm text-sand-200/80 transition-colors hover:text-gold-300">
                            {{ __('Reserve a room') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('booking.catering') }}" class="text-sm text-sand-200/80 transition-colors hover:text-gold-300">
                            {{ __('Order catering') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-16 border-t border-white/10 pt-8">
            <p class="text-xs text-sand-200/60">
                &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </div>
</footer>
