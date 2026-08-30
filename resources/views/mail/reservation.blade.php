{{-- The one template behind every reservation email. It renders a ReservationSummary,
     which flattens hall bookings, room bookings and catering orders into one shape, so
     all three read the same whatever they are. --}}
<x-mail::message>
# {{ $heading }}

{{ $intro }}

<x-mail::panel>
**{{ $reservation->reference }}** — {{ $reservation->type }}
</x-mail::panel>

<x-mail::table>
|                    |                                        |
|:-------------------|---------------------------------------:|
| **{{ __('What') }}**    | {{ $reservation->detail }} |
| **{{ __('When') }}**    | {{ $reservation->occursAtLabel }} |
| **{{ __('Booked by') }}** | {{ $reservation->guestName }} |
| **{{ __('Status') }}**  | {{ $reservation->status->shortLabel() }} |
| **{{ __('Total') }}**   | ₱{{ number_format($reservation->total) }} |
| **{{ __('Paid') }}**    | ₱{{ number_format($reservation->paid) }} |
| **{{ __('Balance') }}** | ₱{{ number_format($reservation->balance) }} |
</x-mail::table>

@if ($outro)
{{ $outro }}
@endif

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
