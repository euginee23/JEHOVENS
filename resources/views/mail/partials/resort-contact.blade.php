{{-- How a guest reaches the resort, from the contact staff set in Settings → Mail.
     Replies already go to the contact email (see ReservationNotification::replyTo()), so
     this spells it out for a guest who would rather call or write fresh. Nothing is shown
     until staff have filled one in. --}}
@if ($contact->hasContact())
**{{ __('Questions?') }}**
@if (filled($contact->contact_email) && filled($contact->contact_phone))
{{ __('Reply to this email, write to us at :email, or call or text :phone.', ['email' => $contact->contact_email, 'phone' => $contact->contact_phone]) }}
@elseif (filled($contact->contact_email))
{{ __('Reply to this email or write to us at :email.', ['email' => $contact->contact_email]) }}
@else
{{ __('Call or text us at :phone.', ['phone' => $contact->contact_phone]) }}
@endif
@endif
