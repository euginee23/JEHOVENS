{{-- A month grid for picking a date range, with days the venue is already sold out on
     closed off. Flux Pro's date-picker is not installed, so this is hand-rolled: the
     parent Livewire component owns the state and exposes selectDate() and shiftMonth().

     Availability is a convenience, not the guard — the booking pages assert the slot is
     free again on submit, under a row lock. --}}
@props([
    'month',
    'start' => '',
    'end' => '',
    'availability',
    'label' => null,
    'hint' => null,
])

@php
    $month = \Illuminate\Support\Carbon::parse($month)->startOfMonth();
    $today = today();

    $gridStart = $month->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::MONDAY);

    // A range is being built when a start is set but its end is not yet chosen.
    $choosingEnd = $start !== '' && $end === '';
@endphp

<div {{ $attributes->class(['space-y-2']) }}>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div class="border border-sand-200 bg-white p-4">
        {{-- Month navigation --}}
        <div class="flex items-center justify-between gap-2">
            <flux:button
                type="button"
                variant="subtle"
                size="sm"
                icon="chevron-left"
                wire:click="shiftMonth(-1)"
                :disabled="$month->lte($today->copy()->startOfMonth())"
                :aria-label="__('Previous month')"
            />

            <p class="font-serif text-base font-medium text-brand-900" aria-live="polite">
                {{ $month->format('F Y') }}
            </p>

            <flux:button
                type="button"
                variant="subtle"
                size="sm"
                icon="chevron-right"
                wire:click="shiftMonth(1)"
                :aria-label="__('Next month')"
            />
        </div>

        {{-- Weekday headings --}}
        <div class="mt-3 grid grid-cols-7 gap-1">
            @foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $weekday)
                <div class="py-1 text-center text-[10px] font-semibold tracking-wider text-brand-800/50 uppercase">
                    {{ $weekday }}
                </div>
            @endforeach
        </div>

        {{-- Days --}}
        <div class="mt-1 grid grid-cols-7 gap-1">
            {{-- Reassigned rather than advanced in place: this application runs on
                 CarbonImmutable, where $day->addDay() alone would never move. --}}
            @for ($day = $gridStart->copy(); $day->lte($gridEnd); $day = $day->addDay())
                @php
                    $date = $day->toDateString();
                    $outsideMonth = ! $day->isSameMonth($month);
                    $past = $day->lt($today);
                    $soldOut = $availability->isUnavailable($date);

                    // Once a start is picked, an end that would book straight through a
                    // sold-out day has to be closed off too.
                    $unreachable = $choosingEnd
                        && $day->gt(\Illuminate\Support\Carbon::parse($start))
                        && ! $availability->rangeIsClear($start, $date);

                    $disabled = $outsideMonth || $past || $soldOut || $unreachable;

                    $isStart = $start !== '' && $date === $start;
                    $isEnd = $end !== '' && $date === $end;
                    $inRange = $start !== '' && $end !== '' && $date > $start && $date < $end;
                    $selected = $isStart || $isEnd;
                @endphp

                @if ($outsideMonth)
                    <div wire:key="pad-{{ $date }}" class="h-9"></div>
                @else
                    <button
                        type="button"
                        wire:key="day-{{ $date }}"
                        @disabled($disabled)
                        @if (! $disabled) wire:click="selectDate('{{ $date }}')" @endif
                        @class([
                            'relative h-9 text-sm font-medium transition',
                            'cursor-not-allowed text-brand-800/25 line-through' => $soldOut && ! $past,
                            'cursor-not-allowed text-brand-800/20' => $past || $unreachable,
                            'bg-brand-800 text-white' => $selected,
                            'bg-sand-100 text-brand-900' => $inRange,
                            'text-brand-900 hover:bg-sand-100' => ! $disabled && ! $selected && ! $inRange,
                            'ring-1 ring-gold-400 ring-inset' => $day->isSameDay($today) && ! $selected,
                        ])
                        @if ($soldOut)
                            aria-label="{{ __(':date — fully booked', ['date' => $day->format('M j')]) }}"
                        @elseif ($availability->isPartial($date))
                            aria-label="{{ __(':date — partly booked (:hours taken)', ['date' => $day->format('M j'), 'hours' => implode(', ', $availability->busyHours($date))]) }}"
                        @endif
                    >
                        {{ $day->day }}

                        {{-- A day with some hours left still gets flagged, so a guest is not
                             surprised when their chosen time is the one already taken. --}}
                        @if ($availability->isPartial($date) && ! $past)
                            <span @class([
                                'absolute inset-x-0 bottom-1 mx-auto size-1 rounded-full',
                                'bg-white/70' => $selected,
                                'bg-gold-500' => ! $selected,
                            ])></span>
                        @endif
                    </button>
                @endif
            @endfor
        </div>

        {{-- Legend --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-sand-200 pt-3 text-[11px] text-brand-800/60">
            <span class="flex items-center gap-1.5">
                <span class="size-1.5 rounded-full bg-gold-500"></span>
                {{ __('Partly booked') }}
            </span>
            <span class="flex items-center gap-1.5">
                <span class="text-brand-800/30 line-through">00</span>
                {{ __('Fully booked') }}
            </span>
        </div>
    </div>

    @if ($hint)
        <p class="text-xs text-brand-800/60">{{ $hint }}</p>
    @endif
</div>
