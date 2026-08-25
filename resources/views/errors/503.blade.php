{{-- Rendered while the application is in maintenance mode, so it sticks to url() and
     assumes nothing beyond a booted framework. --}}
<x-layouts::error
    code="503"
    :title="__('Back in a few minutes')"
    :message="__('The site is briefly offline while we make an update. Bookings already confirmed are unaffected — please check back shortly.')"
>
    <x-slot:actions>
        <x-error-action :href="url('/')">{{ __('Try again') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
