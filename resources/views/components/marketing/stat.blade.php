@props([
    'value',
    'label',
])

<div {{ $attributes->class(['text-center']) }}>
    <p class="font-serif text-4xl font-medium text-brand-800 sm:text-5xl">{{ $value }}</p>
    <p class="eyebrow mt-3 text-[10px] text-brand-700/60">{{ $label }}</p>
</div>
