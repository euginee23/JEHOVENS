{{-- To the guest, as a receipt once their remaining balance has been collected. --}}
<x-mail::message>
# {{ __('Your balance is settled') }}

{{ __('We have received the rest of your payment. Nothing further is owed on this booking.') }}

@include('mail.partials.reservation-details', ['paidInFull' => true])

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
