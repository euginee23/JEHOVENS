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
            'eyebrow flex items-center gap-3',
            'justify-center' => $align === 'center',
            'text-gold-600' => $tone === 'light',
            'text-gold-300' => $tone === 'dark',
        ])>
            <span aria-hidden="true" class="h-px w-8 bg-current opacity-50"></span>
            {{ $eyebrow }}
            @if ($align === 'center')
                <span aria-hidden="true" class="h-px w-8 bg-current opacity-50"></span>
            @endif
        </p>
    @endif

    <h2 @class([
        'mt-5 font-serif text-4xl/tight font-normal text-balance sm:text-5xl/tight',
        'text-brand-900' => $tone === 'light',
        'text-white' => $tone === 'dark',
    ])>
        {{ $title }}
    </h2>

    @if ($description)
        <p @class([
            'mt-5 text-base/7 text-pretty',
            'text-brand-800/70' => $tone === 'light',
            'text-sand-200/80' => $tone === 'dark',
            'mx-auto' => $align === 'center',
        ])>
            {{ $description }}
        </p>
    @endif
</div>
