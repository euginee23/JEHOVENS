{{-- To the guest, once their payment has gone through and the booking is confirmed. --}}
<x-mail::message>
# {{ __('Thanks, :name — you are booked in', ['name' => $reservation->guestName]) }}

{{ __('We have received your payment of ₱:paid and your booking is confirmed. Your payment reference is below — keep this email, and quote your booking reference when you arrive.', ['paid' => number_format($reservation->paid)]) }}

@include('mail.partials.reservation-details')

@if ($reservation->balance >= 1)
{{ __('Please settle the remaining ₱:balance on arrival.', ['balance' => number_format($reservation->balance)]) }}
@endif

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
