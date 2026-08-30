{{-- What the guest has picked, shown at the top of the details panel so it stays visible
     for the whole flow — the price summary further down only appears once a date and a
     duration or head count are filled in. --}}
@props([
    'name' => null,
    'facts' => [],
    'prompt',
])

@if ($name)
    <div {{ $attributes->class(['border-s-2 border-gold-400 bg-sand-100 p-4']) }}>
        <p class="eyebrow text-[10px] text-gold-600">{{ __('Selected') }}</p>

        <p class="mt-1.5 font-serif text-xl font-medium text-brand-900">{{ $name }}</p>

        @if ($facts)
            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                @foreach ($facts as $fact)
                    <span wire:key="fact-{{ $loop->index }}" class="text-xs font-medium text-brand-800">{{ $fact }}</span>
                @endforeach
            </div>
        @endif
    </div>
@else
    <p {{ $attributes->class(['border border-dashed border-sand-200 p-4 text-sm text-brand-800/60']) }}>
        {{ $prompt }}
    </p>
@endif
