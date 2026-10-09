<?php

use App\Mail\TestMail;
use App\Models\ResortSetting;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts::admin')]
#[Title('Mail settings')] class extends Component {
    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $recipient = '';

    public ?string $result = null;

    public bool $succeeded = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $resort = ResortSetting::current();

        $this->contactEmail = (string) $resort->contact_email;
        $this->contactPhone = (string) $resort->contact_phone;
        $this->recipient = Auth::user()->email;
    }

    /**
     * Save the contact guests are pointed to in every booking email.
     */
    public function saveContact(): void
    {
        $validated = $this->validate([
            'contactEmail' => ['nullable', 'string', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
        ], [
            'contactPhone.regex' => __('Use only numbers, spaces, and + ( ) -.'),
        ]);

        ResortSetting::current()->fill([
            'contact_email' => $validated['contactEmail'] ?: null,
            'contact_phone' => trim((string) $validated['contactPhone']) ?: null,
        ])->save();

        Flux::toast(variant: 'success', text: __('Resort contact saved.'));
    }

    /**
     * The mail settings the application is currently running with.
     *
     * @return array{mailer: string, host: string|null, port: int|string|null, scheme: string|null, username: string|null, from_address: string|null, from_name: string|null, alerts_to: string, queued: bool, delivers: bool, from_is_placeholder: bool, app_url: string, app_url_mismatch: bool}
     */
    #[Computed]
    public function settings(): array
    {
        $mailer = (string) config('mail.default');
        $fromDomain = strtolower((string) str((string) config('mail.from.address'))->afterLast('@'));
        $appUrl = (string) config('app.url');

        return [
            'mailer' => $mailer,
            'host' => config("mail.mailers.{$mailer}.host"),
            'port' => config("mail.mailers.{$mailer}.port"),
            'scheme' => config("mail.mailers.{$mailer}.scheme"),
            'username' => config("mail.mailers.{$mailer}.username"),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'alerts_to' => (string) config('resort.notifications.admin_email'),
            'queued' => (bool) config('resort.mail.queued'),
            'delivers' => ! in_array($mailer, ['log', 'array'], true),
            // A From address on a domain nobody owns is accepted by the mail server and
            // then dropped or bounced, which looks exactly like nothing being sent.
            'from_is_placeholder' => $fromDomain === ''
                || in_array($fromDomain, ['example.com', 'example.org', 'example.net', 'localhost'], true)
                || (bool) preg_match('/\.(test|local|localhost|invalid|example)$/', $fromDomain),
            // Links in email are built from APP_URL, not from the request — the
            // unpaid-booking sweeper sends from the command line, where there is no
            // request. A wrong APP_URL sends guests to a dead link.
            'app_url' => $appUrl,
            'app_url_mismatch' => parse_url($appUrl, PHP_URL_HOST) !== request()->getHost(),
        ];
    }

    /**
     * Send a test email straight away, bypassing the queue.
     *
     * Always immediate, so the admin sees the mail server's actual answer here rather
     * than a job that may fail later where nobody is looking.
     */
    public function sendTestEmail(): void
    {
        $this->validate([
            'recipient' => ['required', 'string', 'email', 'max:255'],
        ]);

        $throttleKey = 'test-email:'.Auth::id();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('recipient', __('Too many test emails. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($throttleKey),
            ]));

            return;
        }

        RateLimiter::hit($throttleKey);

        try {
            Mail::to($this->recipient)->send(new TestMail(Auth::user()->name));
        } catch (\Throwable $exception) {
            report($exception);

            $this->succeeded = false;
            $this->result = $exception->getMessage();

            return;
        }

        $this->succeeded = true;
        $this->result = $this->settings['delivers']
            ? __('Test email sent to :email. Check the inbox and the spam folder.', ['email' => $this->recipient])
            : __('The ":mailer" mailer accepted the email, but it does not deliver anything. Set MAIL_MAILER=smtp to send real email.', ['mailer' => $this->settings['mailer']]);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Mail settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mail')" :subheading="__('Set how guests reach the resort, and check how booking emails are sent')">
        <form wire:submit="saveContact" class="my-6 w-full space-y-6">
            <div>
                <flux:heading>{{ __('Resort contact') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Shown in every email a guest receives. When a guest replies to one, the reply goes to this email.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model="contactEmail"
                :label="__('Resort email')"
                type="email"
                autocomplete="email"
                placeholder="bookings@example.com"
                data-test="contact-email"
            />

            <flux:input
                wire:model="contactPhone"
                :label="__('Contact number')"
                type="tel"
                autocomplete="tel"
                placeholder="0917 123 4567"
                data-test="contact-phone"
            />

            <flux:button variant="primary" type="submit" data-test="save-contact-button">
                {{ __('Save') }}
            </flux:button>
        </form>

        <flux:separator variant="subtle" />

        <div class="my-6 w-full space-y-6">
            <flux:heading>{{ __('Sending') }}</flux:heading>

            @unless ($this->settings['delivers'])
                <flux:callout variant="warning" icon="exclamation-triangle" data-test="mailer-not-delivering">
                    <flux:callout.heading>{{ __('Email is not being delivered') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('The ":mailer" mailer only records emails and never sends them. Set MAIL_MAILER=smtp and the MAIL_* settings in .env, then run php artisan config:clear.', ['mailer' => $this->settings['mailer']]) }}
                    </flux:callout.text>
                </flux:callout>
            @endunless

            @if ($this->settings['delivers'] && $this->settings['from_is_placeholder'] && ! app()->isLocal())
                <flux:callout variant="warning" icon="exclamation-triangle" data-test="from-address-placeholder">
                    <flux:callout.heading>{{ __('The From address is a placeholder') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Email is sent from :address, which is not a real domain, so mail servers will drop or bounce it. Set MAIL_FROM_ADDRESS in .env to an address your mail provider lets you send as, then run php artisan config:clear.', ['address' => $this->settings['from_address'] ?: '—']) }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($this->settings['app_url_mismatch'])
                <flux:callout variant="warning" icon="exclamation-triangle" data-test="app-url-mismatch">
                    <flux:callout.heading>{{ __('Links in emails point somewhere else') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Buttons in emails link to :url, but this site is being viewed at :host. Set APP_URL in .env to this site\'s address, then run php artisan config:clear.', ['url' => $this->settings['app_url'], 'host' => request()->getHost()]) }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <dl class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Mailer') }}</dt>
                <dd class="font-medium">{{ $this->settings['mailer'] }}</dd>

                @if ($this->settings['mailer'] === 'smtp')
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Host') }}</dt>
                    <dd class="font-medium break-all">{{ $this->settings['host'] ?: '—' }}:{{ $this->settings['port'] ?: '—' }}</dd>

                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Scheme') }}</dt>
                    <dd class="font-medium">{{ $this->settings['scheme'] ?: __('auto') }}</dd>

                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Username') }}</dt>
                    <dd class="font-medium break-all">{{ $this->settings['username'] ?: '—' }}</dd>
                @endif

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('From') }}</dt>
                <dd class="font-medium break-all">{{ $this->settings['from_name'] }} &lt;{{ $this->settings['from_address'] }}&gt;</dd>

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Links point to') }}</dt>
                <dd class="font-medium break-all">{{ $this->settings['app_url'] }}</dd>

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Booking alerts to') }}</dt>
                <dd class="font-medium break-all">{{ $this->settings['alerts_to'] }}</dd>

                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Delivery') }}</dt>
                <dd class="font-medium">
                    {{ $this->settings['queued'] ? __('Queued (requires a running queue worker)') : __('Immediate') }}
                </dd>
            </dl>

            <flux:separator variant="subtle" />

            <form wire:submit="sendTestEmail" class="space-y-6">
                <flux:input
                    wire:model="recipient"
                    :label="__('Recipient email')"
                    type="email"
                    required
                    autocomplete="email"
                    data-test="test-email-recipient"
                />

                <flux:button variant="primary" type="submit" data-test="send-test-email-button">
                    {{ __('Send test email') }}
                </flux:button>
            </form>

            @if ($result !== null)
                @if ($succeeded)
                    <flux:callout variant="success" icon="check-circle" data-test="test-email-result">
                        <flux:callout.text>{{ $result }}</flux:callout.text>
                    </flux:callout>
                @else
                    <flux:callout variant="danger" icon="x-circle" data-test="test-email-result">
                        <flux:callout.heading>{{ __('The test email could not be sent') }}</flux:callout.heading>
                        <flux:callout.text class="break-all">{{ $result }}</flux:callout.text>
                    </flux:callout>
                @endif
            @endif
        </div>
    </x-pages::settings.layout>
</section>
