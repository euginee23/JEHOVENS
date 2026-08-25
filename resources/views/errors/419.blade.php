{{-- Laravel raises this when the CSRF token has expired — usually a booking form or the
     admin login left open too long. --}}
<x-layouts::error
    code="419"
    :title="__('That form timed out')"
    :message="__('The page sat open long enough for its session to expire, so we did not submit it. Nothing was saved and nothing was charged — open the form again and it will go through.')"
>
    <x-slot:actions>
        <x-error-action :href="url('/#book')">{{ __('Start again') }}</x-error-action>
        <x-error-action :href="url('/')" variant="secondary">{{ __('Back to home') }}</x-error-action>
    </x-slot:actions>
</x-layouts::error>
