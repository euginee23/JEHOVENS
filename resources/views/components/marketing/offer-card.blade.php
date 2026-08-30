@props([
    'title',
    'photos',
    'eyebrow' => null,
    'items' => [],
    'cta' => null,
    'href' => null,
])

<article {{ $attributes->class(['group flex scroll-mt-28 flex-col overflow-hidden bg-white ring-1 ring-sand-200 transition hover:ring-gold-300']) }}>
    <div class="relative aspect-3/4">
        <x-marketing.photo-slideshow
            :photos="$photos"
            dots
            dots-align="end"
            img-class="transition-transform duration-700 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
            class="size-full"
        />

        <div aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-brand-950/60 via-transparent to-transparent"></div>

        @if ($eyebrow)
            <span class="eyebrow absolute left-5 top-5 bg-brand-900/80 px-3.5 py-2 text-[10px] text-gold-200 backdrop-blur-sm">
                {{ $eyebrow }}
            </span>
        @endif

        <h3 class="absolute inset-x-0 bottom-0 p-6 font-serif text-3xl font-medium text-white">{{ $title }}</h3>
    </div>

    <div class="flex flex-1 flex-col p-7">
        <div class="text-sm/7 text-brand-800/70">{{ $slot }}</div>

        @if ($items)
            <ul class="mt-6 space-y-3">
                @foreach ($items as $item)
                    <li class="flex gap-3 text-sm text-brand-800">
                        <svg class="mt-1 size-4 shrink-0 text-gold-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5" />
                        </svg>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($cta && $href)
            <a
                href="{{ $href }}"
                class="eyebrow mt-auto inline-flex items-center gap-3 self-start pt-8 text-[11px] text-brand-700 transition-colors hover:text-gold-600"
            >
                {{ $cta }}
                <span aria-hidden="true" class="h-px w-6 bg-current transition-all group-hover:w-10"></span>
            </a>
        @endif
    </div>
</article>
