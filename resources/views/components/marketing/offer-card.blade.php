@props([
    'title',
    'image',
    'eyebrow' => null,
    'items' => [],
    'cta' => null,
    'href' => null,
])

<article {{ $attributes->class(['group flex scroll-mt-24 flex-col overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-xl hover:shadow-brand-900/10']) }}>
    <div class="relative aspect-4/3 overflow-hidden bg-zinc-100">
        <img
            src="{{ $image }}"
            alt=""
            loading="lazy"
            decoding="async"
            class="size-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
        />

        @if ($eyebrow)
            <span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-700 backdrop-blur">
                {{ $eyebrow }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-6">
        <h3 class="text-xl font-semibold text-zinc-900">{{ $title }}</h3>

        <div class="mt-2 text-sm/6 text-zinc-600">{{ $slot }}</div>

        @if ($items)
            <ul class="mt-5 space-y-2.5">
                @foreach ($items as $item)
                    <li class="flex gap-2.5 text-sm text-zinc-700">
                        <svg class="mt-0.5 size-4.5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
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
                class="mt-auto inline-flex items-center gap-2 self-start pt-6 text-sm font-semibold text-brand-600 transition-colors hover:text-brand-700"
            >
                {{ $cta }}
                <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5">&rarr;</span>
            </a>
        @endif
    </div>
</article>
