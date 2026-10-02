<?php

use App\Models\Payment;
use App\Support\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('layouts::admin')]
#[Title('Sales')]
class extends Component {
    use WithPagination;

    /**
     * The periods the breakdown can cover.
     */
    public const PERIODS = ['today', 'this-month', 'this-year', 'all-time', 'custom'];

    /**
     * The window the chart, the breakdown and the payments list cover. The headline
     * tiles always show today, this month, this year and all time regardless.
     */
    #[Url(except: 'this-month')]
    public string $period = 'this-month';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $until = '';

    /**
     * Back to the first page of payments whenever the window changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'until'], strict: true)) {
            $this->resetPage();
        }
    }

    /**
     * Today, this month, this year and all time — the four figures the resort asked for.
     *
     * @return array{today: int, month: int, year: int, total: int}
     */
    #[Computed]
    public function headline(): array
    {
        $now = CarbonImmutable::now();

        return [
            'total' => SalesReport::collected()['total'],
            'today' => SalesReport::collected($now->startOfDay(), $now->endOfDay())['total'],
            'month' => SalesReport::collected($now->startOfMonth(), $now->endOfMonth())['total'],
            'year' => SalesReport::collected($now->startOfYear(), $now->endOfYear())['total'],
        ];
    }

    /**
     * The start and end of the chosen window.
     *
     * All time starts at the first payment ever received. A custom window with a day left
     * blank falls back to this month's edge on that side, and one entered back to front
     * is turned the right way round rather than showing nothing.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function window(): array
    {
        $now = CarbonImmutable::now();

        [$start, $end] = match ($this->period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'this-year' => [$now->startOfYear(), $now->endOfYear()],
            'all-time' => [
                CarbonImmutable::parse(Payment::query()->min('received_at') ?? $now)->startOfYear(),
                $now->endOfYear(),
            ],
            'custom' => [
                $this->parseDate($this->from)?->startOfDay() ?? $now->startOfMonth(),
                $this->parseDate($this->until)?->endOfDay() ?? $now->endOfMonth(),
            ],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };

        return $start->greaterThan($end) ? [$end->startOfDay(), $start->endOfDay()] : [$start, $end];
    }

    /**
     * Money collected per module within the window.
     *
     * @return array{halls: int, rooms: int, catering: int, total: int}
     */
    #[Computed]
    public function modules(): array
    {
        [$start, $end] = $this->window;

        return SalesReport::collected($start, $end);
    }

    /**
     * The step the trend chart is drawn in: days for a month, months for a year, years
     * for all time. A single day has nothing to trend.
     *
     * @return 'day'|'month'|'year'|null
     */
    public function trendUnit(): ?string
    {
        [$start, $end] = $this->window;

        return match ($this->period) {
            'today' => null,
            'this-month' => 'day',
            'this-year' => 'month',
            'all-time' => 'year',
            default => match (true) {
                $start->diffInDays($end) <= 62 => 'day',
                $start->diffInMonths($end) <= 36 => 'month',
                default => 'year',
            },
        };
    }

    /**
     * Money collected per day, month or year of the window, for the trend chart.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function trend(): array
    {
        $unit = $this->trendUnit();

        if ($unit === null) {
            return [];
        }

        [$start, $end] = $this->window;

        return SalesReport::trend($start, $end, $unit);
    }

    /**
     * Balances still owed on confirmed bookings.
     */
    #[Computed]
    public function stillToCollect(): int
    {
        return SalesReport::stillToCollect();
    }

    /**
     * The verified payments within the window, newest first.
     *
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        [$start, $end] = $this->window;

        return SalesReport::payments($start, $end)
            ->with(['payable', 'recorder'])
            ->latest('received_at')
            ->latest('id')
            ->paginate(15);
    }

    /**
     * Pick a window, clearing any custom days left over from before.
     */
    public function showPeriod(string $period): void
    {
        $this->period = in_array($period, self::PERIODS, strict: true) ? $period : 'this-month';

        if ($this->period !== 'custom') {
            $this->reset(['from', 'until']);
        }

        $this->resetPage();
    }

    /**
     * Read a date typed into the custom range, or null if it is blank or not a date.
     */
    protected function parseDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
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
        $moduleLabels = ['halls' => __('Function halls'), 'rooms' => __('Rooms'), 'catering' => __('Catering')];
        $periodLabels = [
            'today' => __('Today'),
            'this-month' => __('This month'),
            'this-year' => __('This year'),
            'all-time' => __('All time'),
            'custom' => __('Custom'),
        ];
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">{{ __('Sales') }}</h1>
            <p class="mt-2 text-zinc-600">
                {{ __('Money actually received — verified downpayments and balances recorded as paid.') }}
            </p>
        </div>

        @if ($this->stillToCollect > 0)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">{{ __('Still to collect') }}</p>
                <p class="mt-0.5 text-2xl font-bold text-amber-700">₱{{ number_format($this->stillToCollect) }}</p>
            </div>
        @endif
    </div>

    {{-- Headline figures. Fixed windows, unaffected by the period below, so the four
         numbers the resort checks every day are always where they expect them. --}}
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $tiles = [
                ['label' => __('Total sales'), 'value' => $this->headline['total'], 'note' => __('All time'), 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
                ['label' => __("Today's sales"), 'value' => $this->headline['today'], 'note' => now()->format('l, F j'), 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
                ['label' => __('Monthly sales'), 'value' => $this->headline['month'], 'note' => now()->format('F Y'), 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                ['label' => __('Yearly sales'), 'value' => $this->headline['year'], 'note' => now()->format('Y'), 'class' => 'border-violet-200 bg-violet-50 text-violet-700'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div wire:key="headline-{{ $loop->index }}" class="rounded-2xl border p-6 shadow-sm {{ $tile['class'] }}">
                <p class="text-sm font-medium">{{ $tile['label'] }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight">₱{{ number_format($tile['value']) }}</p>
                <p class="mt-1 text-xs text-zinc-600">{{ $tile['note'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Period --}}
    <div class="mt-10 flex flex-wrap items-center gap-2">
        @foreach ($periodLabels as $value => $label)
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

        <span class="ms-1 text-sm text-zinc-500">
            {{ $start->isSameDay($end) ? $start->format('M j, Y') : $start->format('M j, Y').' – '.$end->format('M j, Y') }}
        </span>
    </div>

    @if ($period === 'custom')
        <div class="mt-4 grid max-w-xl gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm sm:grid-cols-2">
            <flux:input wire:model.live="from" :label="__('Received from')" type="date" />
            <flux:input wire:model.live="until" :label="__('Received until')" type="date" />
        </div>
    @endif

    <div class="mt-4 grid gap-6 lg:grid-cols-5">
        {{-- Sales per module. One series, so one colour and no legend: the bar labels
             name each module, and every bar carries its own value. --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h2 class="text-lg font-semibold text-zinc-900">{{ __('Sales per module') }}</h2>
            <p class="mt-1 text-sm text-zinc-500">{{ __('₱:amount collected', ['amount' => number_format($this->modules['total'])]) }}</p>

            @php $moduleMax = max(1, $this->modules['halls'], $this->modules['rooms'], $this->modules['catering']); @endphp

            <div class="mt-6 flex h-56 items-end gap-6 border-b border-zinc-300 px-2" role="img" aria-label="{{ __('Sales per module') }}">
                @foreach ($moduleLabels as $key => $label)
                    @php
                        $amount = $this->modules[$key];
                        $share = $this->modules['total'] > 0 ? round($amount / $this->modules['total'] * 100) : 0;
                    @endphp

                    <div wire:key="module-bar-{{ $key }}" class="group relative flex h-full flex-1 flex-col items-center justify-end">
                        <span class="mb-1.5 text-xs font-semibold text-zinc-700">₱{{ number_format($amount) }}</span>
                        <div
                            class="w-full max-w-20 rounded-t bg-brand-500 transition-colors group-hover:bg-brand-600"
                            style="height: {{ $amount > 0 ? max(2, $amount / $moduleMax * 85) : 0 }}%"
                        ></div>

                        <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-lg bg-zinc-900 px-3 py-2 text-xs text-white shadow-lg group-hover:block">
                            <p class="font-semibold">{{ $label }}</p>
                            <p>₱{{ number_format($amount) }} · {{ $share }}%</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex gap-6 px-2">
                @foreach ($moduleLabels as $key => $label)
                    <span wire:key="module-label-{{ $key }}" class="flex-1 text-center text-xs font-medium text-zinc-600">{{ $label }}</span>
                @endforeach
            </div>
        </div>

        {{-- Daily, monthly or yearly sales, depending on the window. --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm lg:col-span-3">
            @php
                $unit = $this->trendUnit();
                $trendTitle = match ($unit) {
                    'day' => __('Daily sales'),
                    'month' => __('Monthly sales'),
                    'year' => __('Yearly sales'),
                    default => __('Sales today'),
                };
            @endphp

            <h2 class="text-lg font-semibold text-zinc-900">{{ $trendTitle }}</h2>

            @if ($unit === null)
                <p class="mt-1 text-sm text-zinc-500">{{ __('Pick a longer period to see sales over time.') }}</p>
                <p class="mt-6 text-4xl font-bold tracking-tight text-brand-700">₱{{ number_format($this->modules['total']) }}</p>
                <p class="mt-1 text-sm text-zinc-500">{{ trans_choice('{0} No payments received yet today.|{1} From :count payment.|[2,*] From :count payments.', $this->payments->total(), ['count' => $this->payments->total()]) }}</p>
            @else
                @php
                    $trend = $this->trend;
                    $trendMax = max(1, ...array_values($trend));
                    $peakKey = max($trend) > 0 ? array_search(max($trend), $trend, strict: true) : null;
                    $count = count($trend);
                    // Label every bar when there are few, otherwise about six of them, so
                    // the axis stays legible on a thirty-one-day month.
                    $labelEvery = $count <= 12 ? 1 : (int) ceil($count / 6);
                    $format = fn (string $key, bool $long = false) => match ($unit) {
                        'day' => CarbonImmutable::parse($key)->format($long ? 'D, M j, Y' : 'j'),
                        'month' => CarbonImmutable::parse($key)->format($long ? 'F Y' : 'M'),
                        default => CarbonImmutable::parse($key)->format('Y'),
                    };
                @endphp

                <p class="mt-1 text-sm text-zinc-500">
                    @if ($peakKey !== null)
                        {{ __('Best: :when, ₱:amount', ['when' => $format($peakKey, long: true), 'amount' => number_format($trend[$peakKey])]) }}
                    @else
                        {{ __('No payments received in this period.') }}
                    @endif
                </p>

                <div class="mt-6 flex h-56 items-end gap-0.5 border-b border-zinc-300" role="img" aria-label="{{ $trendTitle }}">
                    @foreach ($trend as $key => $amount)
                        <div wire:key="trend-bar-{{ $key }}" class="group relative flex h-full flex-1 flex-col items-center justify-end">
                            @if ($key === $peakKey)
                                <span class="mb-1.5 whitespace-nowrap text-xs font-semibold text-zinc-700">₱{{ number_format($amount) }}</span>
                            @endif

                            <div
                                class="w-full rounded-t bg-brand-500 transition-colors group-hover:bg-brand-600"
                                style="height: {{ $amount > 0 ? max(2, $amount / $trendMax * 85) : 0 }}%"
                            ></div>

                            <div class="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-lg bg-zinc-900 px-3 py-2 text-xs text-white shadow-lg group-hover:block">
                                <p class="font-semibold">{{ $format($key, long: true) }}</p>
                                <p>₱{{ number_format($amount) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex gap-0.5">
                    @foreach (array_keys($trend) as $key)
                        <span wire:key="trend-label-{{ $key }}" class="flex-1 text-center text-xs text-zinc-500">
                            {{ $loop->index % $labelEvery === 0 ? $format($key) : '' }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- The same figures as a table, so nothing depends on reading a bar's height. --}}
    <div class="mt-6 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th scope="col" class="px-6 py-3 font-semibold">{{ __('Module') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Collected') }}</th>
                        <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Share') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-100">
                    @foreach ($moduleLabels as $key => $label)
                        <tr wire:key="module-row-{{ $key }}">
                            <td class="px-6 py-3 font-medium text-zinc-900">{{ $label }}</td>
                            <td class="whitespace-nowrap px-6 py-3 text-right text-zinc-700">₱{{ number_format($this->modules[$key]) }}</td>
                            <td class="whitespace-nowrap px-6 py-3 text-right text-zinc-500">
                                {{ $this->modules['total'] > 0 ? round($this->modules[$key] / $this->modules['total'] * 100).'%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot class="border-t border-zinc-200 bg-zinc-50">
                    <tr>
                        <th scope="row" class="px-6 py-3 font-semibold text-zinc-900">{{ __('Total') }}</th>
                        <td class="whitespace-nowrap px-6 py-3 text-right font-semibold text-zinc-900">₱{{ number_format($this->modules['total']) }}</td>
                        <td class="px-6 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Every payment behind the figures above. --}}
    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-zinc-900">{{ __('Payments received') }}</h2>
            <p class="mt-1 text-sm text-zinc-600">{{ __('Each verified downpayment and settled balance, with who recorded it.') }}</p>
        </div>

        @if ($this->payments->isEmpty())
            <p class="m-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                {{ __('No payments received in this period.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Received') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Booking') }}</th>
                            <th scope="col" class="hidden px-6 py-3 font-semibold md:table-cell">{{ __('For') }}</th>
                            <th scope="col" class="hidden px-6 py-3 font-semibold lg:table-cell">{{ __('Recorded by') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Amount') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->payments as $payment)
                            <tr wire:key="payment-{{ $payment->id }}" class="transition-colors hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-6 py-4 text-zinc-700">{{ $payment->received_at->format('M j, Y · g:i A') }}</td>

                                <td class="px-6 py-4">
                                    <span class="block font-medium text-zinc-900">{{ $payment->payable?->reference ?? '—' }}</span>
                                    <span class="block text-xs text-zinc-500">
                                        {{ $moduleLabels[SalesReport::moduleOf($payment)] }}
                                        @if ($payment->payable)
                                            · {{ $payment->payable->guest_name }}
                                        @endif
                                    </span>
                                </td>

                                <td class="hidden whitespace-nowrap px-6 py-4 text-zinc-700 md:table-cell">
                                    {{ $payment->kind->label() }}
                                    @if ($payment->method)
                                        <span class="text-zinc-500">· {{ strtoupper($payment->method) }}</span>
                                    @endif
                                </td>

                                <td class="hidden whitespace-nowrap px-6 py-4 text-zinc-600 lg:table-cell">{{ $payment->recordedByLabel() }}</td>

                                <td class="whitespace-nowrap px-6 py-4 text-right font-medium text-zinc-900">₱{{ number_format($payment->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->payments->hasPages())
                <div class="border-t border-zinc-200 px-6 py-4">
                    {{ $this->payments->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
