{{-- Marketing nav. The section links are absolute so they also work from the booking
     pages, where those anchors do not exist on the current document.

     In `transparent` mode the bar is `fixed` from the first paint so the hero photo
     starts at the very top of the viewport with the nav floating on it; only its colours
     are bound, so nothing shifts when Alpine boots. Elsewhere it is a solid sticky bar. --}}
@props(['transparent' => false])

@php
    $links = [
        ['label' => __('Home'), 'href' => route('home')],
        ['label' => __('Stay'), 'href' => route('booking.rooms')],
        ['label' => __('Events'), 'href' => route('booking.function-hall')],
        ['label' => __('Dining'), 'href' => route('booking.catering')],
        ['label' => __('Resort'), 'href' => route('home').'#services'],
        ['label' => __('Gallery'), 'href' => route('home').'#gallery'],
        ['label' => __('Contact'), 'href' => route('home').'#contact'],
    ];

    $solid = 'border-sand-200 bg-sand-50/95 text-brand-900 shadow-sm shadow-brand-950/5 backdrop-blur-xl';
    $overlay = 'border-transparent bg-transparent text-white';
@endphp

<header
    x-data="{ open: false, scrolled: false }"
    x-on:scroll.window="scrolled = window.scrollY > 8"
    @class([
        'inset-x-0 top-0 z-40 border-b transition-colors duration-300',
        // The overlay colours are also written statically so the bar is legible on the
        // hero photo before Alpine boots; the object binding below is what removes them.
        'fixed '.$overlay => $transparent,
        'sticky '.$solid => ! $transparent,
    ])
    @if ($transparent)
        x-bind:class="{ '{{ $overlay }}': ! (scrolled || open), '{{ $solid }}': scrolled || open }"
    @endif
>
    <nav class="mx-auto flex h-20 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8" aria-label="{{ __('Main') }}">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <x-marketing.logo-mark class="size-8 shrink-0 text-current opacity-90" />

            <span class="min-w-0">
                <span class="block truncate font-serif text-xl/6 font-semibold tracking-wide">
                    {{ config('app.name') }}
                </span>
                <span class="eyebrow mt-0.5 block text-[10px] opacity-70">{{ __('Garden Resort & Events') }}</span>
            </span>
        </a>

        {{-- Centred on the bar itself, not between its neighbours, so an uneven logo and
             CTA don't push the link row off-centre. --}}
        <div class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-6 lg:flex">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    class="eyebrow relative text-[11px] opacity-80 transition after:absolute after:-bottom-1.5 after:left-0 after:h-px after:w-0 after:bg-gold-400 after:transition-all hover:opacity-100 hover:after:w-full"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Below `sm` the resort name and the burger take the whole bar, so the CTA
             moves into the disclosure panel rather than truncating the resort's name. --}}
        <div class="ms-auto flex shrink-0 items-center gap-2">
            <a
                href="{{ route('home') }}#book"
                class="eyebrow hidden whitespace-nowrap bg-brand-800 px-6 py-3.5 text-[11px] text-white transition hover:bg-brand-700 sm:block"
            >
                {{ __('Book now') }}
            </a>

            <button
                type="button"
                x-on:click="open = ! open"
                x-bind:aria-expanded="open ? 'true' : 'false'"
                aria-controls="mobile-nav"
                class="-me-1 flex size-10 items-center justify-center transition-opacity hover:opacity-70 lg:hidden"
            >
                <span class="sr-only">{{ __('Toggle navigation') }}</span>
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                    <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </nav>

    <div
        id="mobile-nav"
        x-show="open"
        x-cloak
        x-transition.origin.top
        x-on:click.outside="open = false"
        {{-- Overlays the page instead of sitting in the flow: an in-flow panel shifts the
             document up as it collapses, landing anchor links short of their target. --}}
        class="absolute inset-x-0 top-full border-t border-sand-200 bg-sand-50 text-brand-900 shadow-lg shadow-brand-950/10 lg:hidden"
    >
        <div class="px-4 py-4 sm:px-6">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    x-on:click="open = false"
                    class="eyebrow block border-b border-sand-200 py-4 text-brand-800 last:border-0 hover:text-gold-600"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ route('home') }}#book" x-on:click="open = false" class="eyebrow mt-4 block bg-brand-800 px-4 py-4 text-center text-white sm:hidden">
                {{ __('Book now') }}
            </a>
        </div>
    </div>
</header>
