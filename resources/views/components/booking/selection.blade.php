{{-- What the guest has picked, shown at the top of the details panel so it stays visible
     for the whole flow — the price summary further down only appears once a date and a
     duration or head count are filled in. --}}
@props([
    'name' => null,
    'facts' => [],
    'prompt',
])

@if ($name)
    <div {{ $attributes->class(['rounded-2xl border border-brand-200 bg-brand-50/60 p-4']) }}>
        <p class="text-xs font-semibold uppercase tracking-wider text-brand-700">{{ __('Selected') }}</p>

        <p class="mt-1 text-base font-semibold text-zinc-900">{{ $name }}</p>

        @if ($facts)
            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                @foreach ($facts as $fact)
                    <span wire:key="fact-{{ $loop->index }}" class="text-xs font-medium text-brand-800">{{ $fact }}</span>
                @endforeach
            </div>
        @endif
    </div>
@else
    <p {{ $attributes->class(['rounded-2xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-500']) }}>
        {{ $prompt }}
    </p>
@endif
