{{-- Crossfading photo panel, shared by the hero and the offer cards.

     A single photo renders as a plain <img>: no Alpine, no timer, no dots — a slideshow
     of one is just an image. --}}
@props([
    'photos',
    'interval' => 5000,
    'dots' => false,
    'dotsAlign' => 'center',
    // `true` darkens the lower edge so dots and captions stay readable; `'full'` washes
    // the whole panel from the left, for a hero that carries copy over the photograph.
    'scrim' => false,
    // Fills the nearest positioned ancestor instead of sizing itself, so the panel can
    // back a full-bleed section whose height is set by its own content.
    'cover' => false,
    'eager' => false,
    'imgClass' => '',
])

@php
    $photos = array_values($photos);
    $isSlideshow = count($photos) > 1;
@endphp

<div
    @if ($isSlideshow)
        x-data="{ current: 0, total: {{ count($photos) }} }"
        x-init="setInterval(() => current = (current + 1) % total, {{ $interval }})"
    @endif
    {{-- `cover` deliberately carries no negative z-index: the dots live inside this panel
         and must stay clickable, so the section's own copy layers above it instead. --}}
    {{ $attributes->class(['overflow-hidden bg-sand-100', 'relative' => ! $cover, 'absolute inset-0 size-full' => $cover]) }}
>
    @foreach ($photos as $index => $photo)
        <img
            {{-- `url` for an already-resolved URL (uploaded photos on the public disk),
                 otherwise `file` as a path under public/images, e.g. "rooms/room-1.jpg". --}}
            src="{{ $photo['url'] ?? asset('images/'.$photo['file']) }}"
            alt="{{ $photo['alt'] }}"
            width="{{ $photo['width'] }}"
            height="{{ $photo['height'] }}"
            {{-- An optional focal point, as an inline style rather than a class: the value
                 comes from data, and Tailwind only generates the arbitrary `object-[…]`
                 utilities it can find by scanning the source. --}}
            @isset($photo['position']) style="object-position: {{ $photo['position'] }}" @endisset
            @if ($eager && $loop->first) fetchpriority="high" @else loading="lazy" @endif
            decoding="async"
            @if ($isSlideshow)
                x-bind:class="current === {{ $index }} ? 'opacity-100' : 'opacity-0'"
            @endif
            @class([
                'absolute inset-0 size-full object-cover',
                'transition-opacity duration-1000 motion-reduce:transition-none' => $isSlideshow,
                // The first frame is visible server-side so a photo is up before Alpine boots.
                'opacity-100' => $isSlideshow && $loop->first,
                'opacity-0' => $isSlideshow && ! $loop->first,
                $imgClass,
            ])
        />
    @endforeach

    @if ($scrim === 'full')
        <div aria-hidden="true" class="absolute inset-0 bg-linear-to-r from-brand-950/85 via-brand-950/55 to-brand-950/20"></div>
        <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-64 bg-linear-to-t from-brand-950/80 to-transparent"></div>
    @elseif ($scrim)
        <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-32 bg-linear-to-t from-black/40 to-transparent"></div>
    @endif

    @if ($dots && $isSlideshow)
        <div @class([
            'absolute bottom-4 flex gap-2',
            // Centred under the hero; tucked to one side on a card so it clears the badge.
            'left-1/2 -translate-x-1/2 bottom-5' => $dotsAlign === 'center',
            'end-4' => $dotsAlign === 'end',
        ])>
            @foreach ($photos as $index => $photo)
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
    @endif
</div>
