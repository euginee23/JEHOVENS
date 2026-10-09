{{-- To the guest, when an admin cancels their booking. --}}
<x-mail::message>
# {{ __('Your booking has been cancelled') }}

{{ __('This booking has been cancelled and the date has been released. If you did not expect this, please get in touch and we will sort it out.') }}

@include('mail.partials.reservation-details')

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
