<?php

use App\Mail\TestMail;
use App\Models\ResortSetting;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('the mail settings page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('mail-settings.edit'))
        ->assertOk()
        ->assertSee('Send test email');
});

test('guests are sent to the login page', function () {
    $this->get(route('mail-settings.edit'))->assertRedirect(route('login'));
});

test('the recipient defaults to the admin\'s own address', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.mail')
        ->assertSet('recipient', $user->email);
});

test('a test email is sent immediately even when booking mail is queued', function () {
    Mail::fake();
    config(['resort.mail.queued' => true]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->set('recipient', 'inbox@example.com')
        ->call('sendTestEmail')
        ->assertHasNoErrors()
        ->assertSet('succeeded', true);

    Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('inbox@example.com'));
    Mail::assertNothingQueued();
});

test('the recipient must be a valid email address', function () {
    Mail::fake();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->set('recipient', 'not-an-email')
        ->call('sendTestEmail')
        ->assertHasErrors(['recipient' => 'email']);

    Mail::assertNothingSent();
});

test('a mail server failure is shown to the admin', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
    ]);
    Mail::purge('smtp');

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->set('recipient', 'inbox@example.com')
        ->call('sendTestEmail')
        ->assertSet('succeeded', false)
        ->assertSee('The test email could not be sent');
});

test('the page warns when the mailer does not deliver email', function () {
    config(['mail.default' => 'log']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->assertSee('Email is not being delivered');
});

test('test emails are rate limited', function () {
    Mail::fake();

    $component = Livewire::actingAs(User::factory()->create())->test('pages::settings.mail');

    foreach (range(1, 5) as $attempt) {
        $component->call('sendTestEmail')->assertHasNoErrors();
    }

    $component->call('sendTestEmail')->assertHasErrors('recipient');

    Mail::assertSentCount(5);
});

test('the resort contact can be saved', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->set('contactEmail', 'frontdesk@jehovens.com')
        ->set('contactPhone', '+63 917 123 4567')
        ->call('saveContact')
        ->assertHasNoErrors();

    $resort = ResortSetting::current();

    expect($resort->exists)->toBeTrue()
        ->and($resort->contact_email)->toBe('frontdesk@jehovens.com')
        ->and($resort->contact_phone)->toBe('+63 917 123 4567');
});

test('saving again updates the one contact rather than adding another', function () {
    ResortSetting::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->set('contactEmail', 'new@jehovens.com')
        ->call('saveContact');

    expect(ResortSetting::count())->toBe(1)
        ->and(ResortSetting::current()->contact_email)->toBe('new@jehovens.com');
});

test('the saved contact is loaded into the form', function () {
    ResortSetting::factory()->create(['contact_email' => 'frontdesk@jehovens.com', 'contact_phone' => '0917 123 4567']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->assertSet('contactEmail', 'frontdesk@jehovens.com')
        ->assertSet('contactPhone', '0917 123 4567');
});

test('the contact can be cleared', function () {
    ResortSetting::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->set('contactEmail', '')
        ->set('contactPhone', '')
        ->call('saveContact')
        ->assertHasNoErrors();

    expect(ResortSetting::current()->hasContact())->toBeFalse();
});

test('the resort contact is validated', function (string $field, string $value) {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->set($field, $value)
        ->call('saveContact')
        ->assertHasErrors($field);

    expect(ResortSetting::count())->toBe(0);
})->with([
    'email that is not an email' => ['contactEmail', 'not-an-email'],
    'number with letters' => ['contactPhone', '0917-CALL-NOW'],
    'number that is too long' => ['contactPhone', str_repeat('1', 31)],
]);

test('the page warns when email is sent from a placeholder address', function (string $address) {
    config(['mail.default' => 'smtp', 'mail.from.address' => $address]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->assertSee('The From address is a placeholder');
})->with(['hello@example.com', 'bookings@jehovens.test', 'noreply@localhost']);

test('a real From address raises no placeholder warning', function () {
    config(['mail.default' => 'smtp', 'mail.from.address' => 'bookings@jehovens.com']);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.mail')
        ->assertDontSee('The From address is a placeholder');
});

test('the page warns when links in emails would point at another site', function () {
    config(['app.url' => 'http://localhost:8000']);
    $this->actingAs(User::factory()->create());

    $this->get('http://jehovens.com/admin/settings/mail')
        ->assertOk()
        ->assertSee('Links in emails point somewhere else');
});

test('the mail page still opens before the settings table is migrated', function () {
    Exceptions::fake();
    Schema::drop('resort_settings');

    $this->actingAs(User::factory()->create())
        ->get(route('mail-settings.edit'))
        ->assertOk();
});
