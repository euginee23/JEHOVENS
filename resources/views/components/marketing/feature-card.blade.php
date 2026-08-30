@props([
    'title',
    'icon' => null,
])

<div {{ $attributes->class(['group flex flex-col bg-white p-8 ring-1 ring-sand-200 transition hover:ring-gold-300']) }}>
    @if ($icon)
        <span class="flex size-11 items-center justify-center text-gold-600">
            {{ $icon }}
        </span>
    @endif

    <span aria-hidden="true" class="mt-5 h-px w-10 bg-gold-400 transition-all group-hover:w-16"></span>

    <h3 class="mt-5 font-serif text-2xl font-medium text-brand-900">{{ $title }}</h3>

    <div class="mt-3 text-sm/7 text-brand-800/70">{{ $slot }}</div>
</div>
