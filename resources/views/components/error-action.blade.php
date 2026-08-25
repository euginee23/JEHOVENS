{{-- Button used by the error pages' action slots. --}}
@props([
    'href',
    'variant' => 'primary',
])

<a
    href="{{ $href }}"
    @class([
        'inline-flex items-center gap-2 rounded-xl px-6 py-3.5 text-sm font-semibold transition',
        'bg-brand-600 text-white shadow-sm shadow-brand-600/25 hover:bg-brand-700' => $variant === 'primary',
        'border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-50' => $variant === 'secondary',
    ])
>
    {{ $slot }}
</a>
