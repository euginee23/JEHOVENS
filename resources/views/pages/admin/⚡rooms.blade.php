<?php

use App\Livewire\ManagesPhotosComponent;
use App\Models\Room;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::admin')]
#[Title('Rooms')]
class extends ManagesPhotosComponent {

    public ?int $editing = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    /**
     * Rate rows being edited, as `['hours' => int|string, 'price' => int|string]`.
     *
     * @var array<int, array{hours: mixed, price: mixed}>
     */
    public array $rates = [];

    /**
     * Validation rules for the room form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('rooms', 'name')->ignore($this->editing)],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'is_active' => ['boolean'],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.hours' => ['required', 'integer', 'min:1', 'max:168'],
            'rates.*.price' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'rates.required' => __('A room needs at least one duration guests can book.'),
            'rates.min' => __('A room needs at least one duration guests can book.'),
            'rates.*.hours.required' => __('Every rate needs a number of hours.'),
            'rates.*.price.required' => __('Every rate needs a price.'),
        ];
    }

    /**
     * Every room, with its rates, photos, and booking count.
     *
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->with(['rates', 'photos'])
            ->withCount('bookings')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the form for a new room.
     */
    public function addRoom(): void
    {
        $this->reset(['editing', 'name', 'description']);
        $this->is_active = true;
        $this->rates = [['hours' => 6, 'price' => null]];
        $this->resetValidation();

        Flux::modal('room-form')->show();
    }

    /**
     * Open the form for an existing room.
     */
    public function editRoom(int $roomId): void
    {
        $room = Room::with('rates')->findOrFail($roomId);

        $this->editing = $room->id;
        $this->name = $room->name;
        $this->description = $room->description;
        $this->is_active = $room->is_active;
        $this->rates = $room->rates
            ->map(fn ($rate) => ['hours' => $rate->hours, 'price' => $rate->price])
            ->values()
            ->all();
        $this->resetValidation();

        Flux::modal('room-form')->show();
    }

    /**
     * Add an empty rate row.
     */
    public function addRate(): void
    {
        $this->rates[] = ['hours' => null, 'price' => null];
    }

    /**
     * Remove a rate row.
     */
    public function removeRate(int $index): void
    {
        unset($this->rates[$index]);
        $this->rates = array_values($this->rates);
    }

    /**
     * Create or update the room and its rate card.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $hours = array_column($validated['rates'], 'hours');

        if (count($hours) !== count(array_unique($hours))) {
            $this->addError('rates', __('Each duration can only be listed once.'));

            return;
        }

        $room = $this->editing
            ? tap(Room::findOrFail($this->editing))->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'is_active' => $validated['is_active'],
            ])
            : Room::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'is_active' => $validated['is_active'],
                'slug' => str($validated['name'])->slug()->toString(),
                'sort_order' => (int) Room::max('sort_order') + 1,
            ]);

        // Replace the rate card wholesale: it is small, and diffing it would be more code
        // than it saves.
        $room->rates()->delete();

        foreach ($validated['rates'] as $rate) {
            $room->rates()->create(['hours' => (int) $rate['hours'], 'price' => (int) $rate['price']]);
        }

        unset($this->rooms);

        Flux::modal('room-form')->close();
        Flux::toast(variant: 'success', text: __(':name saved.', ['name' => $room->name]));
    }

    /**
     * Show or hide a room on the public booking page.
     */
    public function toggleActive(int $roomId): void
    {
        $room = Room::findOrFail($roomId);
        $room->update(['is_active' => ! $room->is_active]);

        unset($this->rooms);

        Flux::toast(
            variant: 'success',
            text: $room->is_active
                ? __(':name is now bookable.', ['name' => $room->name])
                : __(':name is hidden from guests.', ['name' => $room->name]),
        );
    }

    /**
     * Photos hang off rooms, on the public disk under `rooms/`.
     *
     * @return class-string<Room>
     */
    protected function photoOwnerModel(): string
    {
        return Room::class;
    }

    protected function photoDirectory(): string
    {
        return 'rooms';
    }

    protected function refreshAfterPhotoChange(): void
    {
        unset($this->rooms, $this->photoRecord);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">{{ __('Rooms') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('Rooms guests can book, their rates, and their photos.') }}</p>
        </div>

        <button
            type="button"
            wire:click="addRoom"
            class="rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
        >
            {{ __('Add a room') }}
        </button>
    </div>

    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        @if ($this->rooms->isEmpty())
            <p class="m-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                {{ __('No rooms yet. Add one and it will appear on the public booking page.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-4xl text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Room') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Rates') }}</th>
                            <th scope="col" class="px-6 py-3 text-center font-semibold">{{ __('Photos') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Bookings') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->rooms as $room)
                            <tr wire:key="room-{{ $room->id }}" class="transition-colors hover:bg-zinc-50">
                                <td class="px-6 py-4">
                                    <span class="block font-medium text-zinc-900">{{ $room->name }}</span>
                                    <span class="mt-0.5 block max-w-sm text-xs text-zinc-500">{{ $room->description }}</span>
                                </td>

                                <td class="px-6 py-4">
                                    @if ($room->rates->isEmpty())
                                        <span class="text-xs font-semibold text-amber-700">{{ __('No rates set') }}</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($room->rates as $rate)
                                                <span wire:key="rate-{{ $rate->id }}" class="whitespace-nowrap rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">
                                                    {{ $rate->hours }}h · ₱{{ number_format($rate->price) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-center">
                                    @if ($room->photos->isEmpty())
                                        <span class="text-xs text-zinc-400">{{ __('None') }}</span>
                                    @else
                                        <div class="flex justify-center -space-x-2">
                                            @foreach ($room->photos->take(3) as $photo)
                                                <img
                                                    wire:key="thumb-{{ $photo->id }}"
                                                    src="{{ $photo->url() }}"
                                                    alt=""
                                                    class="size-9 rounded-lg object-cover ring-2 ring-white"
                                                />
                                            @endforeach

                                            @if ($room->photos->count() > 3)
                                                <span class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-xs font-semibold text-zinc-600 ring-2 ring-white">
                                                    +{{ $room->photos->count() - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">{{ number_format($room->bookings_count) }}</td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-brand-50 text-brand-700' => $room->is_active,
                                        'bg-zinc-100 text-zinc-500' => ! $room->is_active,
                                    ])>
                                        {{ $room->is_active ? __('Bookable') : __('Hidden') }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="editRoom({{ $room->id }})" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50">
                                            {{ __('Edit') }}
                                        </button>

                                        <button type="button" wire:click="managePhotos({{ $room->id }})" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50">
                                            {{ __('Photos') }}
                                        </button>

                                        <button type="button" wire:click="toggleActive({{ $room->id }})" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50">
                                            {{ $room->is_active ? __('Hide') : __('Show') }}
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
        {{ __('Rooms are hidden rather than deleted, so their bookings and payment history are never lost.') }}
    </p>

    {{-- Add / edit --}}
    <flux:modal name="room-form" class="w-full md:max-w-xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editing ? __('Edit room') : __('Add a room') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Guests see this on the booking page.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Family Room 201')" required />

            <flux:textarea
                wire:model="description"
                :label="__('Description')"
                :placeholder="__('Beds, air-conditioning, bath, view…')"
                rows="3"
                required
            />

            <div>
                <div class="flex items-center justify-between">
                    <flux:label>{{ __('Rates') }}</flux:label>

                    <button type="button" wire:click="addRate" class="text-sm font-semibold text-brand-600 transition-colors hover:text-brand-700">
                        {{ __('Add a duration') }}
                    </button>
                </div>

                <p class="mt-1 text-xs text-zinc-500">{{ __('How long a guest can stay, and what that costs.') }}</p>

                <div class="mt-3 space-y-3">
                    @foreach ($rates as $index => $rate)
                        <div wire:key="rate-row-{{ $index }}" class="flex items-start gap-3">
                            <div class="flex-1">
                                <flux:input wire:model="rates.{{ $index }}.hours" type="number" min="1" :placeholder="__('Hours')" />
                            </div>

                            <div class="flex-1">
                                <flux:input wire:model="rates.{{ $index }}.price" type="number" min="0" :placeholder="__('Price')" />
                            </div>

                            <button
                                type="button"
                                wire:click="removeRate({{ $index }})"
                                @disabled(count($rates) === 1)
                                class="mt-1 flex size-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                <span class="sr-only">{{ __('Remove this rate') }}</span>
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                @error('rates')
                    <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <flux:switch
                wire:model="is_active"
                :label="__('Bookable')"
                :description="__('Turn this off to hide the room from guests without losing its bookings.')"
            />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700">
                    {{ __('Save room') }}
                </button>
            </div>
        </form>
    </flux:modal>

    {{-- Photos --}}
    <flux:modal name="photo-manager" class="w-full md:max-w-xl">
        @if ($this->photoRecord)
            <x-admin.photo-manager :record="$this->photoRecord" :uploads="$uploads" :limit="Room::PHOTO_LIMIT" />
        @endif
    </flux:modal>
</div>
