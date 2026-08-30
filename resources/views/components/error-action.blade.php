{{-- Button used by the error pages' action slots. --}}
@props([
    'href',
    'variant' => 'primary',
])

<a
    href="{{ $href }}"
    @class([
        'eyebrow inline-flex items-center gap-2 px-6 py-4 text-[11px] transition',
        'bg-brand-800 text-white hover:bg-brand-700' => $variant === 'primary',
        'border border-sand-200 bg-white text-brand-800 hover:border-brand-300 hover:bg-sand-50' => $variant === 'secondary',
    ])
>
    {{ $slot }}
</a>
