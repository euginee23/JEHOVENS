{{-- Decorative blurred gradient blobs used behind the light and dark CTA sections. --}}
@props(['variant' => 'hero'])

<div aria-hidden="true" {{ $attributes->class(['pointer-events-none absolute inset-0 overflow-hidden']) }}>
    @if ($variant === 'hero')
        <div class="absolute -left-32 -top-40 size-[36rem] rounded-full bg-brand-200/40 blur-3xl"></div>
        <div class="absolute -right-24 -top-24 size-[30rem] rounded-full bg-gold-300/20 blur-3xl"></div>
        <div class="absolute -bottom-48 left-1/3 size-[32rem] rounded-full bg-sand-200/60 blur-3xl"></div>
        <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-gold-400/50 to-transparent"></div>
    @else
        <div class="absolute -left-20 top-0 size-96 rounded-full bg-brand-700/40 blur-3xl"></div>
        <div class="absolute -right-16 bottom-0 size-96 rounded-full bg-gold-500/15 blur-3xl"></div>
    @endif
</div>
