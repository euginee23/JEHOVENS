<x-layouts::error
    code="403"
    :title="__('That area is staff only')"
    :message="__('You need to be signed in as staff to open this page. Booking a hall, room, or catering never requires an account — you can do all of that as a guest.')"
>
    <x-slot:actions>
        <x-error-action :href="url('/')">{{ __('Back to home') }}</x-error-action>
        <x-error-action :href="url('/admin/login')" variant="secondary">{{ __('Staff sign in') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
