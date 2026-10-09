{{-- To the resort, so someone knows a new booking is waiting to be reviewed. --}}
<x-mail::message>
# {{ __('New :type booking to review', ['type' => mb_strtolower($reservation->type)]) }}

{{ __(':name has booked :detail and says the payment is sent. It is waiting in the admin panel as pending.', ['name' => $reservation->guestName, 'detail' => $reservation->detail]) }}

@include('mail.partials.reservation-details')

{{ __('Contact: :email', ['email' => $reservation->guestEmail]) }}

<x-mail::button :url="route('admin.bookings')">
{{ __('Open bookings') }}
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
