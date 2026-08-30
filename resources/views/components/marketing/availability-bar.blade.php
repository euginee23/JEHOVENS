{{-- Availability search floating over the hero.

     A plain GET form rather than a Livewire component: it works before Alpine boots and
     the result is a shareable URL. The room booking page reads `date`, `entry`, and
     `hours` off the query string and pre-fills itself.

     The fields are this resort's shape, not a generic hotel's: a booking is one room for
     one duration block, with no guest count, so "check out" is a duration and the third
     cell is the arrival time. --}}
@php
    $entryHours = [];

    // Same wording as the booking page's own entry-time select.
    for ($hour = \App\Models\Room::ENTRY_OPENS_AT; $hour <= \App\Models\Room::ENTRY_CLOSES_AT; $hour++) {
        $entryHours[$hour] = sprintf('%d:00 %s', $hour % 12 ?: 12, $hour >= 12 ? 'PM' : 'AM');
    }

    // Durations come from the rates the resort actually sells rather than a hardcoded
    // list; with none on record the cell is dropped and the booking page simply opens
    // without a duration pre-selected.
    $durations = \App\Models\RoomRate::query()
        ->distinct()
        ->orderBy('hours')
        ->pluck('hours')
        ->all();

    $cellClass = 'flex flex-1 items-center gap-3 px-5 py-4 sm:px-6';
    $labelClass = 'eyebrow text-[10px] text-sand-200/60';
    $controlClass = 'w-full cursor-pointer appearance-none bg-transparent text-sm font-medium text-white outline-hidden [color-scheme:dark] focus-visible:underline';
@endphp

<form
    method="GET"
    action="{{ route('booking.rooms') }}"
    {{ $attributes->class(['flex flex-col bg-brand-900/95 shadow-2xl shadow-brand-950/40 ring-1 ring-white/10 backdrop-blur-md sm:flex-row sm:items-stretch']) }}
>
    <div class="{{ $cellClass }} border-b border-white/10 sm:border-b-0 sm:border-e">
        <svg class="size-5 shrink-0 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3v2.5M16.5 3v2.5M3.5 9h17M4.5 6h15a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z" />
        </svg>

        <span class="min-w-0 flex-1">
            <label for="availability-date" class="{{ $labelClass }} block">{{ __('Check in') }}</label>
            <input
                id="availability-date"
                type="date"
                name="date"
                min="{{ today()->toDateString() }}"
                value="{{ today()->toDateString() }}"
                class="{{ $controlClass }} mt-1"
            />
        </span>
    </div>

    <div class="{{ $cellClass }} border-b border-white/10 sm:border-b-0 sm:border-e">
        <svg class="size-5 shrink-0 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.5V12l3.5 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>

        <span class="min-w-0 flex-1">
            <label for="availability-entry" class="{{ $labelClass }} block">{{ __('Arrival') }}</label>
            <select id="availability-entry" name="entry" class="{{ $controlClass }} mt-1">
                @foreach ($entryHours as $hour => $label)
                    <option value="{{ $hour }}" class="text-brand-900" @selected($hour === 14)>{{ $label }}</option>
                @endforeach
            </select>
        </span>
    </div>

    @if ($durations)
        <div class="{{ $cellClass }} border-b border-white/10 sm:border-b-0 sm:border-e">
            <svg class="size-5 shrink-0 text-gold-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 17v-6.5A1.5 1.5 0 0 1 4.5 9h15a1.5 1.5 0 0 1 1.5 1.5V17M3 17v2m0-2h18m0 0v2M6.5 9V7a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v2" />
            </svg>

            <span class="min-w-0 flex-1">
                <label for="availability-hours" class="{{ $labelClass }} block">{{ __('Duration') }}</label>
                <select id="availability-hours" name="hours" class="{{ $controlClass }} mt-1">
                    @foreach ($durations as $hours)
                        <option value="{{ $hours }}" class="text-brand-900" @selected($hours === 24)>
                            {{ $hours === 24 ? __('24 hours (overnight)') : __(':hours hours', ['hours' => $hours]) }}
                        </option>
                    @endforeach
                </select>
            </span>
        </div>
    @endif

    <button
        type="submit"
        class="eyebrow bg-gold-500 px-8 py-5 text-[11px] text-brand-950 transition hover:bg-gold-400 sm:py-4"
    >
        {{ __('Check availability') }}
    </button>
</form>
