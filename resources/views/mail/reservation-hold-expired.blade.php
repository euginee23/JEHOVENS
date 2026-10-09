{{-- To the guest, when their dates were released because payment never arrived. From
     their side nothing happened and then the booking was gone, so this explains why. --}}
<x-mail::message>
# {{ __('We have released your dates') }}

{{ __('We held the dates below while you paid, but the payment was never completed, so they have gone back on sale. Nothing has been charged.') }}

@include('mail.partials.reservation-details')

{{ __('Still want them? Book again — they may well still be free.') }}

<x-mail::button :url="route('home')">
{{ __('Book again') }}
</x-mail::button>

@include('mail.partials.resort-contact')

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
