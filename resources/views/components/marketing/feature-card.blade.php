@props([
    'title',
    'icon' => null,
])

<div {{ $attributes->class(['flex flex-col rounded-2xl border border-zinc-200 bg-white p-6 transition hover:border-brand-200 hover:shadow-lg hover:shadow-brand-900/5']) }}>
    @if ($icon)
        <span class="flex size-11 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500 text-white shadow-sm shadow-brand-600/25">
            {{ $icon }}
        </span>
    @endif

    <h3 class="mt-5 text-lg font-semibold text-zinc-900">{{ $title }}</h3>

    <div class="mt-2 text-sm/6 text-zinc-600">{{ $slot }}</div>
</div>
