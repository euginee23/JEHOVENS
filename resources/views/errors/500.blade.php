{{-- Only rendered when APP_DEBUG is false; with debug on, Laravel shows its own trace. --}}
<x-layouts::error
    code="500"
    :title="__('Something broke on our end')"
    :message="__('This one is our fault, not yours. If you were part-way through a booking it was not recorded and no payment was taken, so nothing is owing — please try again in a moment.')"
>
    <x-slot:actions>
        <x-error-action :href="url('/')">{{ __('Back to home') }}</x-error-action>
    </x-slot:actions>

    <p class="mt-10 text-sm text-brand-800/60">
        {{ __('If it keeps happening, please contact the resort so we can sort your booking out by hand.') }}
    </p>
</x-layouts::error>
