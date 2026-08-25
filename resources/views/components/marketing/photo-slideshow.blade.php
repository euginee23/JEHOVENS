{{-- Crossfading photo panel, shared by the hero and the offer cards.

     A single photo renders as a plain <img>: no Alpine, no timer, no dots — a slideshow
     of one is just an image. --}}
@props([
    'photos',
    'interval' => 5000,
    'dots' => false,
    'dotsAlign' => 'center',
    'scrim' => false,
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
    {{ $attributes->class(['relative overflow-hidden bg-zinc-100']) }}
>
    @foreach ($photos as $index => $photo)
        <img
            {{-- `url` for an already-resolved URL (uploaded photos on the public disk),
                 otherwise `file` as a path under public/images, e.g. "rooms/room-1.jpg". --}}
            src="{{ $photo['url'] ?? asset('images/'.$photo['file']) }}"
            alt="{{ $photo['alt'] }}"
            width="{{ $photo['width'] }}"
            height="{{ $photo['height'] }}"
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

    @if ($scrim)
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
