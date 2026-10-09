{{-- To the guest, when an admin confirms their booking. --}}
<x-mail::message>
# {{ __('Your booking is confirmed') }}

{{ __('Your payment has cleared and the date is held for you. We look forward to having you.') }}

@include('mail.partials.reservation-details')

@if ($reservation->balance >= 1)
{{ __('Please settle the remaining ₱:balance on arrival.', ['balance' => number_format($reservation->balance)]) }}
@endif

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
