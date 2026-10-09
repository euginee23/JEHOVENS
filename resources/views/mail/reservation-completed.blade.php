{{-- To the guest, when an admin marks their booking as done. This is the last email
     about the booking, so it closes it off plainly: it is finished, nothing is owed,
     and they are welcome back. Marking a booking done settles any balance with it. --}}
<x-mail::message>
# {{ __('Thank you, :name', ['name' => $reservation->guestName]) }}

{{ __('Your reservation is now complete. Thank you for choosing :app — we hope everything went well and that you enjoyed your time with us.', ['app' => config('app.name')]) }}

@include('mail.partials.reservation-details', ['paidInFull' => true])

{{ __('Your booking has been paid in full and is now closed. There is nothing more you need to do.') }}

{{ __('If anything was not right during your visit, just reply to this email — we read every one.') }}

<x-mail::button :url="route('home')">
{{ __('Book your next visit') }}
</x-mail::button>

{{ __('We hope to see you again soon,') }}<br>
{{ config('app.name') }}
</x-mail::message>
