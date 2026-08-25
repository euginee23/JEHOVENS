@props([
    'value',
    'label',
])

<div class="text-center">
    <p class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">{{ $value }}</p>
    <p class="mt-1.5 text-sm text-zinc-500">{{ $label }}</p>
</div>
