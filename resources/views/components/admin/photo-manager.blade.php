{{-- Photo panel shared by the halls, rooms, and catering admin screens.

     The host component must expose `uploads`, `uploadPhotos()`, `removePhoto($id)` and
     `movePhoto($id, $direction)` — see the `ManagesPhotos` trait. --}}
@props([
    'record',
    'uploads' => [],
    'limit',
])

<div class="space-y-6">
    <div>
        <flux:heading size="lg">{{ __('Photos — :name', ['name' => $record->name]) }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('Up to :max photos. The first one leads on the booking page.', ['max' => $limit]) }}
        </flux:text>
    </div>

    @if ($record->photos->isEmpty())
        <p class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">
            {{ __('No photos yet. Guests see a text-only card until you add one.') }}
        </p>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            @foreach ($record->photos as $photo)
                <div wire:key="photo-{{ $photo->id }}" class="relative overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100">
                    <img src="{{ $photo->url() }}" alt="{{ $photo->alt }}" class="aspect-4/3 w-full object-cover" />

                    @if ($loop->first)
                        <span class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-700 backdrop-blur">
                            {{ __('Lead') }}
                        </span>
                    @endif

                    <div class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-linear-to-t from-black/70 to-transparent p-2">
                        <div class="flex gap-1">
                            <button
                                type="button"
                                wire:click="movePhoto({{ $photo->id }}, 'up')"
                                @disabled($loop->first)
                                class="flex size-7 items-center justify-center rounded-md bg-white/90 text-zinc-700 transition hover:bg-white disabled:opacity-30"
                            >
                                <span class="sr-only">{{ __('Move earlier') }}</span>
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7" />
                                </svg>
                            </button>

                            <button
                                type="button"
                                wire:click="movePhoto({{ $photo->id }}, 'down')"
                                @disabled($loop->last)
                                class="flex size-7 items-center justify-center rounded-md bg-white/90 text-zinc-700 transition hover:bg-white disabled:opacity-30"
                            >
                                <span class="sr-only">{{ __('Move later') }}</span>
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                                </svg>
                            </button>
                        </div>

                        <button
                            type="button"
                            wire:click="removePhoto({{ $photo->id }})"
                            wire:confirm="{{ __('Remove this photo? The file is deleted for good.') }}"
                            class="flex size-7 items-center justify-center rounded-md bg-white/90 text-red-600 transition hover:bg-white"
                        >
                            <span class="sr-only">{{ __('Remove photo') }}</span>
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($record->photos->count() < $limit)
        <form wire:submit="uploadPhotos" class="space-y-4">
            <flux:input
                type="file"
                wire:model="uploads"
                multiple
                accept="image/jpeg,image/png,image/webp"
                :label="__('Add photos')"
                :description="__('JPG, PNG, or WebP. Large photos are resized to :width px automatically.', ['width' => \App\Support\PhotoStore::MAX_WIDTH])"
            />

            <div wire:loading wire:target="uploads" class="text-sm text-zinc-500">{{ __('Uploading…') }}</div>

            @if ($uploads)
                <button type="submit" class="w-full rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700">
                    {{ trans_choice('{1} Save :count photo|[2,*] Save :count photos', count($uploads), ['count' => count($uploads)]) }}
                </button>
            @endif
        </form>
    @else
        <p class="text-sm text-zinc-500">
            {{ __('This has the maximum of :count photos. Remove one to add another.', ['count' => $limit]) }}
        </p>
    @endif
</div>
