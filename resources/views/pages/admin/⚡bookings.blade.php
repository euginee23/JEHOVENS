<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::admin')]
#[Title('Bookings')]
class extends Component {
    use WithPagination;

    /**
     * Which kind of reservation is on screen. Each tab queries its own table with its own
     * paginator — a union across two differently-shaped tables would page incorrectly.
     */
    #[Url(except: 'halls')]
    public string $type = 'halls';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $venue = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $until = '';

    public ?int $viewing = null;

    /**
     * Reset to the first page whenever a filter narrows the result set.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'venue', 'from', 'until', 'type'], strict: true)) {
            $this->resetPage();
        }
    }

    /**
     * Switch between halls and rooms, clearing filters that do not carry across.
     */
    public function showType(string $type): void
    {
        $this->type = in_array($type, ['halls', 'rooms', 'catering'], strict: true) ? $type : 'halls';

        // A hall id means nothing in the rooms tab.
        $this->reset(['venue', 'viewing']);
        $this->resetPage();
        $this->refreshLists();
    }

    /**
     * Whether the rooms tab is showing.
     */
    public function showingRooms(): bool
    {
        return $this->type === 'rooms';
    }

    /**
     * Whether the catering tab is showing.
     */
    public function showingCatering(): bool
    {
        return $this->type === 'catering';
    }

    /**
     * The model class backing the current tab.
     *
     * @return class-string<Model>
     */
    protected function model(): string
    {
        return match ($this->type) {
            'rooms' => RoomBooking::class,
            'catering' => CateringOrder::class,
            default => Booking::class,
        };
    }

    /**
     * The column holding the date of the event or stay.
     */
    protected function dateColumn(): string
    {
        return match ($this->type) {
            'rooms' => 'starts_at',
            'catering' => 'event_date',
            default => 'booking_date',
        };
    }

    /**
     * The relation naming what was booked.
     */
    protected function venueRelation(): string
    {
        return match ($this->type) {
            'rooms' => 'room',
            'catering' => 'package',
            default => 'hall',
        };
    }

    /**
     * The foreign key that relation hangs off.
     */
    protected function venueColumn(): string
    {
        return match ($this->type) {
            'rooms' => 'room_id',
            'catering' => 'catering_package_id',
            default => 'hall_id',
        };
    }

    /**
     * Venues to populate the filter dropdown, matching the current tab.
     *
     * @return Collection<int, Model>
     */
    #[Computed]
    public function venues(): Collection
    {
        return match ($this->type) {
            'rooms' => Room::query()->orderBy('name')->get(),
            'catering' => CateringPackage::query()->orderBy('name')->get(),
            default => Hall::query()->orderBy('name')->get(),
        };
    }

    /**
     * The filtered booking list.
     *
     * @return LengthAwarePaginator<int, Model>
     */
    #[Computed]
    public function bookings(): LengthAwarePaginator
    {
        $model = $this->model();

        return $model::query()
            ->with($this->venueRelation())
            ->when($this->search !== '', function (Builder $query) {
                $term = '%'.$this->search.'%';

                $query->where(fn (Builder $q) => $q
                    ->where('reference', 'like', $term)
                    ->orWhere('guest_name', 'like', $term)
                    ->orWhere('guest_phone', 'like', $term)
                    ->orWhere('guest_email', 'like', $term));
            })
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->venue !== '', fn (Builder $q) => $q->where($this->venueColumn(), $this->venue))
            ->when($this->from !== '', fn (Builder $q) => $q->whereDate($this->dateColumn(), '>=', $this->from))
            ->when($this->until !== '', fn (Builder $q) => $q->whereDate($this->dateColumn(), '<=', $this->until))
            ->orderByDesc($this->dateColumn())
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * How many bookings of each type exist, for the tab labels.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function typeCounts(): array
    {
        return [
            'halls' => Booking::count(),
            'rooms' => RoomBooking::count(),
            'catering' => CateringOrder::count(),
        ];
    }

    /**
     * Totals for the status chips, within the current tab.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $model = $this->model();

        $counts = $model::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            '' => array_sum($counts),
            ...collect(BookingStatus::cases())
                ->mapWithKeys(fn (BookingStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])
                ->all(),
        ];
    }

    /**
     * Money still owed across the current tab.
     */
    #[Computed]
    public function outstanding(): int
    {
        $model = $this->model();

        return (int) $model::query()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereNull('balance_settled_at')
            ->sum('balance');
    }

    /**
     * The booking open in the detail panel.
     */
    #[Computed]
    public function booking(): ?Model
    {
        if (! $this->viewing) {
            return null;
        }

        $model = $this->model();

        return $model::with($this->venueRelation())->find($this->viewing);
    }

    /**
     * Open the detail panel. Named `viewBooking` rather than `view` because Livewire's
     * Component base class already defines a `view()` method.
     */
    public function viewBooking(int $bookingId): void
    {
        $this->viewing = $bookingId;

        Flux::modal('booking-detail')->show();
    }

    /**
     * Move a booking to a new status. Named `moveTo` rather than `transition` because
     * Livewire's Component base class already defines `transition($type, $skip)`.
     */
    public function moveTo(int $bookingId, string $status): void
    {
        $model = $this->model();
        $booking = $model::findOrFail($bookingId);
        $target = BookingStatus::from($status);

        if (! $booking->transitionTo($target)) {
            Flux::toast(variant: 'warning', text: __('A :from booking cannot be marked :to.', [
                'from' => strtolower($booking->status->shortLabel()),
                'to' => strtolower($target->shortLabel()),
            ]));

            return;
        }

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __(':reference is now :status.', [
            'reference' => $booking->reference,
            'status' => strtolower($target->shortLabel()),
        ]));
    }

    /**
     * Record that the remaining balance has been collected.
     */
    public function settleBalance(int $bookingId): void
    {
        $model = $this->model();
        $booking = $model::findOrFail($bookingId);

        if (! $booking->settleBalance()) {
            Flux::toast(variant: 'warning', text: __('That booking has nothing left to pay.'));

            return;
        }

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Balance of ₱:amount recorded for :reference.', [
            'amount' => number_format($booking->balance),
            'reference' => $booking->reference,
        ]));
    }

    /**
     * Clear every filter, keeping the current tab.
     */
    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'venue', 'from', 'until']);
        $this->resetPage();
    }

    /**
     * Whether any filter is narrowing the list.
     */
    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->status !== '' || $this->venue !== ''
            || $this->from !== '' || $this->until !== '';
    }

    /**
     * Drop the cached computed properties so the table reflects the change.
     */
    protected function refreshLists(): void
    {
        unset($this->bookings, $this->counts, $this->outstanding, $this->booking, $this->venues, $this->typeCounts);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">{{ __('Bookings') }}</h1>
            <p class="mt-2 text-zinc-600">
                {{ __('Confirm downpayments, record balances, and cancel reservations.') }}
            </p>
        </div>

        @if ($this->outstanding > 0)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">{{ __('Still to collect') }}</p>
                <p class="mt-0.5 text-2xl font-bold text-amber-700">₱{{ number_format($this->outstanding) }}</p>
            </div>
        @endif
    </div>

    {{-- Type tabs --}}
    <div class="mt-8 flex gap-1 border-b border-zinc-200" role="tablist">
        @foreach (['halls' => __('Function halls'), 'rooms' => __('Rooms'), 'catering' => __('Catering')] as $value => $label)
            <button
                type="button"
                wire:key="tab-{{ $value }}"
                wire:click="showType('{{ $value }}')"
                role="tab"
                aria-selected="{{ $type === $value ? 'true' : 'false' }}"
                @class([
                    '-mb-px border-b-2 px-4 py-3 text-sm font-semibold transition',
                    'border-brand-600 text-brand-700' => $type === $value,
                    'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800' => $type !== $value,
                ])
            >
                {{ $label }}
                <span class="ms-1 text-xs text-zinc-400">{{ $this->typeCounts[$value] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Status chips --}}
    <div class="mt-6 flex flex-wrap gap-2">
        @php
            $chips = ['' => __('All')] + collect(BookingStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->shortLabel()])->all();
        @endphp

        @foreach ($chips as $value => $label)
            <button
                type="button"
                wire:key="chip-{{ $value ?: 'all' }}"
                wire:click="$set('status', '{{ $value }}')"
                @class([
                    'rounded-full px-4 py-2 text-sm font-semibold transition',
                    'bg-brand-600 text-white shadow-sm shadow-brand-600/25' => $status === $value,
                    'border border-zinc-200 bg-white text-zinc-600 hover:border-brand-300 hover:text-brand-700' => $status !== $value,
                ])
            >
                {{ $label }}
                <span @class(['ms-1 text-xs', 'text-white/70' => $status === $value, 'text-zinc-400' => $status !== $value])>
                    {{ $this->counts[$value] ?? 0 }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="mt-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:input
                wire:model.live.debounce.400ms="search"
                :label="__('Search')"
                :placeholder="__('Reference, name, phone, email')"
                icon="magnifying-glass"
            />

            @php
                $venueLabel = match ($type) { 'rooms' => __('Room'), 'catering' => __('Package'), default => __('Hall') };
                $venueAll = match ($type) { 'rooms' => __('All rooms'), 'catering' => __('All packages'), default => __('All halls') };
                $dateLabel = match ($type) { 'rooms' => __('Check-in'), 'catering' => __('Event date'), default => __('Event') };
            @endphp

            <flux:select wire:model.live="venue" :label="$venueLabel" :placeholder="$venueAll">
                @foreach ($this->venues as $option)
                    <flux:select.option wire:key="venue-{{ $type }}-{{ $option->id }}" :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live="from" :label="__(':label from', ['label' => $dateLabel])" type="date" />
            <flux:input wire:model.live="until" :label="__(':label until', ['label' => $dateLabel])" type="date" />
        </div>

        @if ($this->isFiltered())
            <button type="button" wire:click="resetFilters" class="mt-4 text-sm font-semibold text-brand-600 transition-colors hover:text-brand-700">
                {{ __('Clear filters') }}
            </button>
        @endif
    </div>

    {{-- Bookings --}}
    <div class="mt-6 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        @if ($this->bookings->isEmpty())
            <p class="m-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                {{ $this->isFiltered()
                    ? __('No bookings match these filters.')
                    : match ($type) {
                        'rooms' => __('No room bookings yet.'),
                        'catering' => __('No catering orders yet.'),
                        default => __('No function hall bookings yet.'),
                    } }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-4xl text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Reference') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Guest') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">
                                {{ match ($type) {
                                    'rooms' => __('Room & stay'),
                                    'catering' => __('Package & event'),
                                    default => __('Hall & date'),
                                } }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Total') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Balance') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->bookings as $row)
                            <tr wire:key="booking-{{ $type }}-{{ $row->id }}" class="transition-colors hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <button type="button" wire:click="viewBooking({{ $row->id }})" class="font-medium text-brand-600 hover:text-brand-700">
                                        {{ $row->reference }}
                                    </button>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="block text-zinc-900">{{ $row->guest_name }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $row->guest_phone }}</span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="block text-zinc-900">
                                        {{ match ($type) {
                                            'rooms' => $row->room->name,
                                            'catering' => $row->package->name,
                                            default => $row->hall->name,
                                        } }}
                                    </span>

                                    {{-- Catering has no `hours` column; it is priced per head. --}}
                                    <span class="block text-xs text-zinc-500">
                                        @if ($this->showingCatering())
                                            {{ $row->event_date->format('M j, Y') }}
                                            · {{ trans_choice('{1} :count guest|[2,*] :count guests', $row->guests, ['count' => number_format($row->guests)]) }}
                                        @else
                                            {{ $this->showingRooms() ? $row->starts_at->format('M j, Y · g:i A') : $row->booking_date->format('M j, Y') }}
                                            · {{ trans_choice('{1} :count hour|[2,*] :count hours', $row->hours, ['count' => $row->hours]) }}
                                        @endif
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right font-medium text-zinc-900">₱{{ number_format($row->total) }}</td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    @if ($row->balance === 0)
                                        <span class="text-xs font-semibold text-emerald-700">{{ __('Paid in full') }}</span>
                                    @elseif ($row->balance_settled_at)
                                        <span class="text-xs font-semibold text-emerald-700">{{ __('Settled') }}</span>
                                    @else
                                        <span class="font-medium text-amber-700">₱{{ number_format($row->balance) }}</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row->status->classes() }}">
                                        {{ $row->status->shortLabel() }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        @foreach ($row->status->transitions() as $target)
                                            <button
                                                type="button"
                                                wire:key="move-{{ $type }}-{{ $row->id }}-{{ $target->value }}"
                                                wire:click="moveTo({{ $row->id }}, '{{ $target->value }}')"
                                                @class([
                                                    'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                                    'bg-brand-600 text-white hover:bg-brand-700' => $target === BookingStatus::Confirmed,
                                                    'border border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-50' => $target !== BookingStatus::Confirmed,
                                                ])
                                            >
                                                {{ __('Mark :status', ['status' => strtolower($target->shortLabel())]) }}
                                            </button>
                                        @endforeach

                                        @if ($row->hasOutstandingBalance() && $row->status === BookingStatus::Confirmed)
                                            <button
                                                type="button"
                                                wire:click="settleBalance({{ $row->id }})"
                                                class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                                            >
                                                {{ __('Balance paid') }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->bookings->hasPages())
                <div class="border-t border-zinc-200 px-6 py-4">
                    {{ $this->bookings->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Detail --}}
    <flux:modal name="booking-detail" class="w-full md:max-w-lg">
        @if ($this->booking)
            @php
                $b = $this->booking;
                $isRoom = $this->showingRooms();
                $isCatering = $this->showingCatering();

                $when = match (true) {
                    $isRoom => [
                        __('Check-in') => $b->starts_at->format('l, F j, Y \a\t g:i A'),
                        __('Check-out') => $b->ends_at->format('l, F j, Y \a\t g:i A'),
                        __('Arrive by') => $b->arriveBy()->format('g:i A'),
                    ],
                    $isCatering => [
                        __('Event date') => $b->event_date->format('l, F j, Y'),
                        __('Guests') => number_format($b->guests),
                        __('Per head') => '₱'.number_format($b->price_per_head),
                        __('Skirting') => $b->include_skirting ? __('Included') : __('Not included'),
                    ],
                    default => [
                        __('Date') => $b->booking_date->format('l, F j, Y'),
                        __('Time') => sprintf('%d:00 %s – %d:00 %s', $b->start_hour % 12 ?: 12, $b->start_hour >= 12 ? 'PM' : 'AM', $b->end_hour % 12 ?: 12, $b->end_hour >= 12 ? 'PM' : 'AM'),
                    ],
                };

                $rows = [
                    __('Guest') => $b->guest_name,
                    __('Phone') => $b->guest_phone,
                    __('Email') => $b->guest_email,
                    ...$when,
                    __('Total') => '₱'.number_format($b->total),
                    __('Paid') => '₱'.number_format($b->amountPaid()),
                    __('Balance') => $b->balance === 0
                        ? __('Paid in full')
                        : ($b->balance_settled_at
                            ? __('Settled :date', ['date' => $b->balance_settled_at->format('M j, Y')])
                            : '₱'.number_format($b->balance)),
                    __('Booked on') => $b->created_at->format('M j, Y g:i A'),
                ];
            @endphp

            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $b->reference }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ match (true) {
                            $isRoom => $b->room->name,
                            $isCatering => $b->package->name,
                            default => $b->hall->name,
                        } }}
                    </flux:text>
                </div>

                <dl class="divide-y divide-zinc-200 border-y border-zinc-200 text-sm">
                    @foreach ($rows as $label => $value)
                        <div class="flex justify-between gap-6 py-3" wire:key="detail-{{ $loop->index }}">
                            <dt class="text-zinc-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-zinc-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $b->status->classes() }}">
                        {{ $b->status->label() }}
                    </span>

                    @foreach ($b->status->transitions() as $target)
                        <button
                            type="button"
                            wire:key="detail-move-{{ $target->value }}"
                            wire:click="moveTo({{ $b->id }}, '{{ $target->value }}')"
                            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50"
                        >
                            {{ __('Mark :status', ['status' => strtolower($target->shortLabel())]) }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:modal>
</div>
