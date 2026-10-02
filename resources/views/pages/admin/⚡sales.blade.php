<?php

use App\Support\SalesReport;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts::admin')]
#[Title('Sales')]
class extends Component {
    /**
     * The window the figures cover: this-month, last-month, this-year or custom.
     */
    #[Url(except: 'this-month')]
    public string $period = 'this-month';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $until = '';

    /**
     * The start and end of the chosen window.
     *
     * A custom window with a day left blank falls back to this month's edge on that side,
     * and one entered back to front is turned the right way round rather than showing zero.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function window(): array
    {
        $now = CarbonImmutable::now();

        [$start, $end] = match ($this->period) {
            'last-month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this-year' => [$now->startOfYear(), $now->endOfYear()],
            'custom' => [
                $this->parseDate($this->from)?->startOfDay() ?? $now->startOfMonth(),
                $this->parseDate($this->until)?->endOfDay() ?? $now->endOfMonth(),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return $start->greaterThan($end) ? [$end->startOfDay(), $start->endOfDay()] : [$start, $end];
    }

    /**
     * Sales per reservation type and in total for the chosen window.
     *
     * @return array<string, array{bookings: int, sales: int, collected: int, toCollect: int}>
     */
    #[Computed]
    public function report(): array
    {
        [$start, $end] = $this->window;

        return SalesReport::between($start, $end);
    }

    /**
     * Month-by-month totals, shown when the whole year is on screen.
     *
     * @return array<string, array{bookings: int, sales: int, collected: int, toCollect: int}>
     */
    #[Computed]
    public function months(): array
    {
        if ($this->period !== 'this-year') {
            return [];
        }

        $year = CarbonImmutable::now()->startOfYear();

        return collect(range(0, 11))
            ->mapWithKeys(function (int $offset) use ($year) {
                $month = $year->addMonths($offset);

                return [$month->format('F') => SalesReport::between($month, $month->endOfMonth())['total']];
            })
            ->all();
    }

    /**
     * Pick a preset window, clearing any custom days left over from before.
     */
    public function showPeriod(string $period): void
    {
        $this->period = in_array($period, ['this-month', 'last-month', 'this-year', 'custom'], strict: true) ? $period : 'this-month';

        if ($this->period !== 'custom') {
            $this->reset(['from', 'until']);
        }
    }

    /**
     * Read a date typed into the custom range, or null if it is blank or not a date.
     */
    protected function parseDate(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}; ?>

<div>
    @php
        [$start, $end] = $this->window;
        $total = $this->report['total'];
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">{{ __('Sales') }}</h1>
            <p class="mt-2 text-zinc-600">
                {{ __('Confirmed and completed reservations placed between :from and :until.', [
                    'from' => $start->format('M j, Y'),
                    'until' => $end->format('M j, Y'),
                ]) }}
            </p>
        </div>
    </div>

    {{-- Period --}}
    <div class="mt-8 flex flex-wrap gap-2">
        @foreach (['this-month' => __('This month'), 'last-month' => __('Last month'), 'this-year' => __('This year'), 'custom' => __('Custom')] as $value => $label)
            <button
                type="button"
                wire:key="period-{{ $value }}"
                wire:click="showPeriod('{{ $value }}')"
                @class([
                    'rounded-full px-4 py-2 text-sm font-semibold transition',
                    'bg-brand-600 text-white shadow-sm shadow-brand-600/25' => $period === $value,
                    'border border-zinc-200 bg-white text-zinc-600 hover:border-brand-300 hover:text-brand-700' => $period !== $value,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($period === 'custom')
        <div class="mt-4 grid max-w-xl gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:grid-cols-2">
            <flux:input wire:model.live="from" :label="__('Placed from')" type="date" />
            <flux:input wire:model.live="until" :label="__('Placed until')" type="date" />
        </div>
    @endif

    {{-- Stat tiles --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $tiles = [
                ['label' => __('Gross sales'), 'value' => '₱'.number_format($total['sales']), 'note' => __('Full value of what was booked'), 'class' => 'text-zinc-900'],
                ['label' => __('Collected'), 'value' => '₱'.number_format($total['collected']), 'note' => __('Downpayments and settled balances'), 'class' => 'text-brand-700'],
                ['label' => __('Still to collect'), 'value' => '₱'.number_format($total['toCollect']), 'note' => __('Balances owed on confirmed bookings'), 'class' => $total['toCollect'] > 0 ? 'text-amber-600' : 'text-zinc-900'],
                ['label' => __('Bookings'), 'value' => number_format($total['bookings']), 'note' => __('Confirmed and completed'), 'class' => 'text-zinc-900'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div wire:key="sales-tile-{{ $loop->index }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-zinc-500">{{ $tile['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight {{ $tile['class'] }}">{{ $tile['value'] }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $tile['note'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- By type --}}
    @php
        $columns = fn (array $row) => [
            number_format($row['bookings']),
            '₱'.number_format($row['sales']),
            '₱'.number_format($row['collected']),
            '₱'.number_format($row['toCollect']),
        ];
    @endphp

    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-zinc-900">{{ __('By type') }}</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-semibold">{{ __('Type') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Bookings') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Sales') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Collected') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('To collect') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-100">
                    @foreach (['halls' => __('Function halls'), 'rooms' => __('Rooms'), 'catering' => __('Catering')] as $key => $label)
                        <tr wire:key="sales-type-{{ $key }}">
                            <td class="whitespace-nowrap px-6 py-4 font-medium text-zinc-900">{{ $label }}</td>
                            @foreach ($columns($this->report[$key]) as $cell)
                                <td wire:key="sales-type-{{ $key }}-{{ $loop->index }}" class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>

                <tfoot class="border-t border-zinc-200 bg-zinc-50">
                    <tr>
                        <th scope="row" class="px-6 py-4 font-semibold text-zinc-900">{{ __('Total') }}</th>
                        @foreach ($columns($total) as $cell)
                            <td wire:key="sales-total-{{ $loop->index }}" class="whitespace-nowrap px-6 py-4 text-right font-semibold text-zinc-900">{{ $cell }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- By month, for the year view --}}
    @if ($this->months !== [])
        <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('By month') }}</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Month') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Bookings') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Sales') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Collected') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('To collect') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->months as $month => $row)
                            <tr wire:key="sales-month-{{ $loop->index }}">
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-zinc-900">{{ __($month) }}</td>
                                @foreach ($columns($row) as $cell)
                                    <td wire:key="sales-month-{{ $loop->parent->index }}-{{ $loop->index }}" class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="mt-6 text-xs text-zinc-500">
        {{ __('Bookings awaiting payment and cancelled bookings are left out, since no money has been verified for them.') }}
    </p>
</div>
