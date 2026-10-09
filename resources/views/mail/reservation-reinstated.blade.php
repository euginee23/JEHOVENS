{{-- To the guest, when an admin moves a booking back to pending. --}}
<x-mail::message>
# {{ __('Your booking is back with us') }}

{{ __('This booking has been reinstated and is with us for review again. We will confirm shortly.') }}

@include('mail.partials.reservation-details')

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
