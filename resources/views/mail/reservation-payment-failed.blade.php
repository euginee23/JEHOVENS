{{-- To the guest, when their payment did not go through. The dates are still held until
     the hold expires, so this invites them to try again rather than cancelling. --}}
<x-mail::message>
# {{ __('We could not take your payment') }}

{{ __('Your payment of ₱:amount for the booking below was not completed, so it is not confirmed yet. We are holding your dates for a short while — start the booking again to try another payment method.', ['amount' => number_format($reservation->paid)]) }}

@include('mail.partials.reservation-details')

{{ __('If you think this is a mistake, reply to this email with your reference and we will look into it.') }}

@include('mail.partials.resort-contact')

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
