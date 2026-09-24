{{-- A month grid for picking the days of a booking, with days the venue is already sold
     out on closed off. Flux Pro's date-picker is not installed, so this is hand-rolled:
     the parent Livewire component owns the state and exposes toggleDate(), clearDates()
     and shiftMonth().

     Each day is a toggle, not one end of a range. Tapping a day takes it, tapping it
     again gives it back, and the days need not be consecutive — which is what lets a
     guest book three separate Saturdays, and undo a mis-tap without starting over.

     Availability is a convenience, not the guard — the booking pages assert the slot is
     free again on submit, under a row lock. --}}
@props([
    'month',
    'dates' => [],
    'availability',
    'label' => null,
    'hint' => null,
])

@php
    $month = \Illuminate\Support\Carbon::parse($month)->startOfMonth();
    $today = today();

    $gridStart = $month->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::MONDAY);
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
                    $selected = in_array($date, $dates, strict: true);

                    // A chosen day that has since sold out stays tappable, so the guest can
                    // still take it back off their booking.
                    $disabled = $outsideMonth || $past || ($soldOut && ! $selected);
                @endphp

                @if ($outsideMonth)
                    <div wire:key="pad-{{ $date }}" class="h-9"></div>
                @else
                    <button
                        type="button"
                        wire:key="day-{{ $date }}"
                        aria-pressed="{{ $selected ? 'true' : 'false' }}"
                        @disabled($disabled)
                        @if (! $disabled) wire:click="toggleDate('{{ $date }}')" @endif
                        @class([
                            'relative h-9 text-sm font-medium transition',
                            'cursor-not-allowed text-brand-800/25 line-through' => $soldOut && ! $past && ! $selected,
                            'cursor-not-allowed text-brand-800/20' => $past,
                            'bg-brand-800 text-white' => $selected,
                            'text-brand-900 hover:bg-sand-100' => ! $disabled && ! $selected,
                            'ring-1 ring-gold-400 ring-inset' => $day->isSameDay($today) && ! $selected,
                        ])
                        @if ($selected)
                            aria-label="{{ __(':date — chosen, tap to remove', ['date' => $day->format('M j')]) }}"
                        @elseif ($soldOut)
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

        {{-- What has been chosen, and a way out of it. Without this a guest who has
             scrolled to another month has no idea what they are holding. --}}
        @if ($dates !== [])
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-sand-200 pt-3">
                <p class="text-xs font-medium text-brand-900">
                    {{ trans_choice('{1} :count day chosen|[2,*] :count days chosen', count($dates), ['count' => count($dates)]) }}
                </p>

                <flux:button type="button" variant="subtle" size="xs" wire:click="clearDates">
                    {{ __('Clear dates') }}
                </flux:button>
            </div>
        @endif

        {{-- Legend --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-sand-200 pt-3 text-[11px] text-brand-800/60">
            <span class="flex items-center gap-1.5">
                <span class="size-2.5 bg-brand-800"></span>
                {{ __('Chosen') }}
            </span>
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
