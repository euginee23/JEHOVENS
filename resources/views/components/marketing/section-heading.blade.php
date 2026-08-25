@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'center',
    'tone' => 'light',
])

<div @class([
    'max-w-2xl',
    'mx-auto text-center' => $align === 'center',
])>
    @if ($eyebrow)
        <p @class([
            'text-sm font-semibold uppercase tracking-wider',
            'text-brand-600' => $tone === 'light',
            'text-brand-300' => $tone === 'dark',
        ])>
            {{ $eyebrow }}
        </p>
    @endif

    <h2 @class([
        'mt-3 text-3xl font-bold tracking-tight text-balance sm:text-4xl',
        'text-zinc-900' => $tone === 'light',
        'text-white' => $tone === 'dark',
    ])>
        {{ $title }}
    </h2>

    @if ($description)
        <p @class([
            'mt-4 text-lg/8 text-pretty',
            'text-zinc-600' => $tone === 'light',
            'text-brand-100' => $tone === 'dark',
            'mx-auto' => $align === 'center',
        ])>
            {{ $description }}
        </p>
    @endif
</div>
