<?php

use App\Livewire\ManagesPhotosComponent;
use App\Models\CateringPackage;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('layouts::admin')]
#[Title('Catering')]
class extends ManagesPhotosComponent {

    public ?int $editing = null;

    public string $name = '';

    public string $description = '';

    public ?int $price_per_head = null;

    public ?int $skirting_price = null;

    public ?int $minimum_guests = null;

    public bool $is_active = true;

    /**
     * Validation rules for the package form.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('catering_packages', 'name')->ignore($this->editing)],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'price_per_head' => ['required', 'integer', 'min:1', 'max:100000'],
            'skirting_price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'minimum_guests' => ['required', 'integer', 'min:1', 'max:'.CateringPackage::MAX_GUESTS],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'price_per_head.min' => __('A package has to cost something per head.'),
            'minimum_guests.max' => __('The minimum cannot exceed the :count guest cap on an order.', ['count' => CateringPackage::MAX_GUESTS]),
        ];
    }

    /**
     * Every package, with its photos and order count.
     *
     * @return Collection<int, CateringPackage>
     */
    #[Computed]
    public function packages(): Collection
    {
        return CateringPackage::query()
            ->with('photos')
            ->withCount('orders')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the form for a new package.
     */
    public function addPackage(): void
    {
        $this->reset(['editing', 'name', 'description', 'price_per_head', 'skirting_price']);
        $this->minimum_guests = 20;
        $this->is_active = true;
        $this->resetValidation();

        Flux::modal('package-form')->show();
    }

    /**
     * Open the form for an existing package.
     */
    public function editPackage(int $packageId): void
    {
        $package = CateringPackage::findOrFail($packageId);

        $this->editing = $package->id;
        $this->name = $package->name;
        $this->description = $package->description;
        $this->price_per_head = $package->price_per_head;
        $this->skirting_price = $package->skirting_price;
        $this->minimum_guests = $package->minimum_guests;
        $this->is_active = $package->is_active;
        $this->resetValidation();

        Flux::modal('package-form')->show();
    }

    /**
     * Create or update the package.
     */
    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editing) {
            $package = CateringPackage::findOrFail($this->editing);
            // The slug is the public identifier; renaming must not break it.
            $package->update($validated);
        } else {
            $package = CateringPackage::create([
                ...$validated,
                'slug' => str($validated['name'])->slug()->toString(),
                'sort_order' => (int) CateringPackage::max('sort_order') + 1,
            ]);
        }

        unset($this->packages);

        Flux::modal('package-form')->close();
        Flux::toast(variant: 'success', text: __(':name saved.', ['name' => $package->name]));
    }

    /**
     * Show or hide a package on the public ordering page.
     */
    public function toggleActive(int $packageId): void
    {
        $package = CateringPackage::findOrFail($packageId);
        $package->update(['is_active' => ! $package->is_active]);

        unset($this->packages);

        Flux::toast(
            variant: 'success',
            text: $package->is_active
                ? __(':name is now orderable.', ['name' => $package->name])
                : __(':name is hidden from guests.', ['name' => $package->name]),
        );
    }

    /**
     * Photos hang off packages, on the public disk under `catering/`.
     *
     * @return class-string<CateringPackage>
     */
    protected function photoOwnerModel(): string
    {
        return CateringPackage::class;
    }

    protected function photoDirectory(): string
    {
        return 'catering';
    }

    protected function refreshAfterPhotoChange(): void
    {
        unset($this->packages, $this->photoRecord);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">{{ __('Catering') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('Packages guests can order, their per-head price, and their photos.') }}</p>
        </div>

        <button
            type="button"
            wire:click="addPackage"
            class="rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
        >
            {{ __('Add a package') }}
        </button>
    </div>

    <div class="mt-8 rounded-2xl border border-zinc-200 bg-white shadow-sm">
        @if ($this->packages->isEmpty())
            <p class="m-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
                {{ __('No packages yet. Add one and it will appear on the public ordering page.') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-4xl text-left text-sm">
                    <thead class="border-b border-zinc-200 text-xs uppercase tracking-wider text-zinc-500">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Package') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Per head') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Skirting') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Min. guests') }}</th>
                            <th scope="col" class="px-6 py-3 text-center font-semibold">{{ __('Photos') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Orders') }}</th>
                            <th scope="col" class="px-6 py-3 font-semibold">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->packages as $package)
                            <tr wire:key="package-{{ $package->id }}" class="transition-colors hover:bg-zinc-50">
                                <td class="px-6 py-4">
                                    <span class="block font-medium text-zinc-900">{{ $package->name }}</span>
                                    <span class="mt-0.5 block max-w-sm text-xs text-zinc-500">{{ $package->description }}</span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right font-medium text-zinc-900">₱{{ number_format($package->price_per_head) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">₱{{ number_format($package->skirting_price) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">{{ number_format($package->minimum_guests) }}</td>

                                <td class="px-6 py-4 text-center">
                                    @if ($package->photos->isEmpty())
                                        <span class="text-xs text-zinc-400">{{ __('None') }}</span>
                                    @else
                                        <div class="flex justify-center -space-x-2">
                                            @foreach ($package->photos->take(3) as $photo)
                                                <img wire:key="thumb-{{ $photo->id }}" src="{{ $photo->url() }}" alt="" class="size-9 rounded-lg object-cover ring-2 ring-white" />
                                            @endforeach

                                            @if ($package->photos->count() > 3)
                                                <span class="flex size-9 items-center justify-center rounded-lg bg-zinc-100 text-xs font-semibold text-zinc-600 ring-2 ring-white">
                                                    +{{ $package->photos->count() - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right text-zinc-700">{{ number_format($package->orders_count) }}</td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-brand-50 text-brand-700' => $package->is_active,
                                        'bg-zinc-100 text-zinc-500' => ! $package->is_active,
                                    ])>
                                        {{ $package->is_active ? __('Orderable') : __('Hidden') }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="editPackage({{ $package->id }})" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50">
                                            {{ __('Edit') }}
                                        </button>

                                        <button type="button" wire:click="managePhotos({{ $package->id }})" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50">
                                            {{ __('Photos') }}
                                        </button>

                                        <button type="button" wire:click="toggleActive({{ $package->id }})" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-50">
                                            {{ $package->is_active ? __('Hide') : __('Show') }}
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
        {{ __('Packages are hidden rather than deleted, so their orders and payment history are never lost.') }}
    </p>

    {{-- Add / edit --}}
    <flux:modal name="package-form" class="w-full md:max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editing ? __('Edit package') : __('Add a package') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Guests see this on the ordering page.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Mediterranean Mezze')" required />

            <flux:textarea
                wire:model="description"
                :label="__('Description')"
                :placeholder="__('What is on the menu…')"
                rows="3"
                required
            />

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="price_per_head" :label="__('Per head')" type="number" min="1" placeholder="450" required />
                <flux:input wire:model="skirting_price" :label="__('Skirting')" type="number" min="0" placeholder="5000" required />
                <flux:input wire:model="minimum_guests" :label="__('Min. guests')" type="number" min="1" placeholder="20" required />
            </div>

            <flux:switch
                wire:model="is_active"
                :label="__('Orderable')"
                :description="__('Turn this off to hide the package from guests without losing its orders.')"
            />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700">
                    {{ __('Save package') }}
                </button>
            </div>
        </form>
    </flux:modal>

    {{-- Photos --}}
    <flux:modal name="photo-manager" class="w-full md:max-w-xl">
        @if ($this->photoRecord)
            <x-admin.photo-manager :record="$this->photoRecord" :uploads="$uploads" :limit="CateringPackage::PHOTO_LIMIT" />
        @endif
    </flux:modal>
</div>
