{{-- Sent from the Mail settings page. --}}
<x-mail::message>
# {{ __('Test email') }}

{{ __('This message confirms that :app can send email. If you are reading it, the mail settings are working.', ['app' => config('app.name')]) }}

<x-mail::panel>
{{ __('Sent by: :name', ['name' => $sentBy]) }}<br>
{{ __('Mailer: :mailer', ['mailer' => $mailer]) }}<br>
{{ __('Sent at: :time', ['time' => $sentAt]) }}
</x-mail::panel>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
