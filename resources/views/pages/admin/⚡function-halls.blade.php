<?php

use App\Livewire\ManagesPhotosComponent;
use App\Models\Hall;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::admin')]
#[Title('Function halls')]
class extends ManagesPhotosComponent {

    /**
     * The hall being edited, or null when adding a new one.
     */
    public ?int $editing = null;

    public string $name = '';

    public string $description = '';

    public ?int $capacity = null;

    public ?int $rent_price = null;

    public ?int $skirting_price = null;

    public bool $is_active = true;

    /**
     * Validation rules for the hall form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'],
            'rent_price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'skirting_price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Every hall, with a count of the bookings that depend on it.
     *
     * @return Collection<int, Hall>
     */
    #[Computed]
    public function halls(): Collection
    {
        return Hall::query()
            ->with('photos')
            ->withCount('bookings')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the form for a new hall.
     */
    public function addHall(): void
    {
        $this->reset(['editing', 'name', 'description', 'capacity', 'rent_price', 'skirting_price']);
        $this->is_active = true;
        $this->resetValidation();

        Flux::modal('hall-form')->show();
    }

    /**
     * Open the form for an existing hall.
     */
    public function editHall(int $hallId): void
    {
        $hall = Hall::findOrFail($hallId);

        $this->editing = $hall->id;
        $this->name = $hall->name;
        $this->description = $hall->description;
        $this->capacity = $hall->capacity;
        $this->rent_price = $hall->rent_price;
        $this->skirting_price = $hall->skirting_price;
        $this->is_active = $hall->is_active;
        $this->resetValidation();

        Flux::modal('hall-form')->show();
    }

    /**
     * Create or update the hall.
     */
    public function save(): void
    {
        $validated = $this->validate([
            ...$this->rules(),
            'name' => [
                'required', 'string', 'min:2', 'max:100',
                Rule::unique('halls', 'name')->ignore($this->editing),
            ],
        ]);

        if ($this->editing) {
            $hall = Hall::findOrFail($this->editing);
            // The slug is the public identifier; renaming a hall must not break it.
            $hall->update($validated);
        } else {
            $hall = Hall::create([
                ...$validated,
                'slug' => str($validated['name'])->slug()->toString(),
                'sort_order' => (int) Hall::max('sort_order') + 1,
            ]);
        }

        unset($this->halls);

        Flux::modal('hall-form')->close();
        Flux::toast(variant: 'success', text: __(':name saved.', ['name' => $hall->name]));
    }

    /**
     * Show or hide a hall on the public booking page.
     */
    public function toggleActive(int $hallId): void
    {
        $hall = Hall::findOrFail($hallId);
        $hall->update(['is_active' => ! $hall->is_active]);

        unset($this->halls);

        Flux::toast(
            variant: 'success',
            text: $hall->is_active
                ? __(':name is now bookable.', ['name' => $hall->name])
                : __(':name is hidden from guests.', ['name' => $hall->name]),
        );
    }

    /**
     * Photos hang off halls, on the public disk under `function-hall/`.
     *
     * @return class-string<Hall>
     */
    protected function photoOwnerModel(): string
    {
        return Hall::class;
    }

    protected function photoDirectory(): string
    {
        return 'function-hall';
    }

    protected function refreshAfterPhotoChange(): void
    {
        unset($this->halls, $this->photoRecord);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">{{ __('Function halls') }}</h1>
            <p class="mt-2 text-zinc-600">
                {{ __('Halls guests can book, and what each one costs.') }}
            </p>
        </div>

        <button
            type="button"
            wire:click="addHall"
            class="rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
        >
            {{ __('Add a hall') }}
        </button>
    </div>

    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        @if ($this->halls->isEmpty())
            <p class="m-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                {{ __('No halls yet. Add one and it will appear on the public booking page.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-3xl text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Hall') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Capacity') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Rent / 4 hrs') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Skirting') }}</th>
                            <th scope="col" class="px-6 py-3 text-center font-semibold">{{ __('Photos') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Bookings') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->halls as $hall)
                            <tr wire:key="hall-{{ $hall->id }}" class="transition-colors hover:bg-zinc-50">
                                <td class="px-6 py-4">
                                    <span class="block font-medium text-zinc-900">{{ $hall->name }}</span>
                                    <span class="mt-0.5 block max-w-md text-xs text-zinc-500">{{ $hall->description }}</span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">{{ number_format($hall->capacity) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-medium text-zinc-900">₱{{ number_format($hall->rent_price) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">₱{{ number_format($hall->skirting_price) }}</td>
                                <td class="px-6 py-4 text-center">
                                    @if ($hall->photos->isEmpty())
                                        <span class="text-xs text-zinc-400">{{ __('None') }}</span>
                                    @else
                                        <div class="flex justify-center -space-x-2">
                                            @foreach ($hall->photos->take(3) as $photo)
                                                <img wire:key="thumb-{{ $photo->id }}" src="{{ $photo->url() }}" alt="" class="size-9 rounded-lg object-cover ring-2 ring-white" />
                                            @endforeach

                                            @if ($hall->photos->count() > 3)
                                                <span class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-xs font-semibold text-zinc-600 ring-2 ring-white">
                                                    +{{ $hall->photos->count() - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">{{ number_format($hall->bookings_count) }}</td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-brand-50 text-brand-700' => $hall->is_active,
                                        'bg-zinc-100 text-zinc-500' => ! $hall->is_active,
                                    ])>
                                        {{ $hall->is_active ? __('Bookable') : __('Hidden') }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="editHall({{ $hall->id }})"
                                            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50"
                                        >
                                            {{ __('Edit') }}
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="managePhotos({{ $hall->id }})"
                                            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50"
                                        >
                                            {{ __('Photos') }}
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="toggleActive({{ $hall->id }})"
                                            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50"
                                        >
                                            {{ $hall->is_active ? __('Hide') : __('Show') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="mt-4 text-sm text-zinc-500">
        {{ __('Halls are hidden rather than deleted, so their bookings and payment history are never lost.') }}
    </p>

    {{-- Photos --}}
    <flux:modal name="photo-manager" class="w-full md:max-w-xl">
        @if ($this->photoRecord)
            <x-admin.photo-manager :record="$this->photoRecord" :uploads="$uploads" :limit="Hall::PHOTO_LIMIT" />
        @endif
    </flux:modal>

    {{-- Add / edit --}}
    <flux:modal name="hall-form" class="w-full md:max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editing ? __('Edit hall') : __('Add a hall') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Guests see this on the booking page.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Grand Ballroom')" required />

            <flux:textarea
                wire:model="description"
                :label="__('Description')"
                :placeholder="__('Capacity, amenities, location, features…')"
                rows="3"
                required
            />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="capacity" :label="__('Capacity')" type="number" min="1" placeholder="500" required />
                <flux:input wire:model="rent_price" :label="__('Rent / 4 hrs')" type="number" min="0" placeholder="8000" required />
                <flux:input wire:model="skirting_price" :label="__('Skirting')" type="number" min="0" placeholder="5000" required />
            </div>

            <flux:switch
                wire:model="is_active"
                :label="__('Bookable')"
                :description="__('Turn this off to hide the hall from guests without losing its bookings.')"
            />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <button
                    type="submit"
                    class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
                >
                    {{ __('Save hall') }}
                </button>
            </div>
        </form>
    </flux:modal>
</div>
