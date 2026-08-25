<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('typing /admin as a guest lands on the login page', function () {
    $this->get('/admin')
        ->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in to the admin area');
});

test('the admin login lives under the admin prefix', function () {
    expect(route('login', absolute: false))->toBe('/admin/login');
});

test('a signed-in admin gets the dashboard at /admin', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertOk();
});

test('the old dashboard and settings urls redirect into the admin area', function () {
    $this->get('/dashboard')->assertRedirect('/admin');
    $this->get('/settings')->assertRedirect('/admin/settings');
});

test('the settings index redirects to profile without doubling the admin prefix', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/settings')->assertRedirect('/admin/settings/profile');
});

test('settings pages live under the admin prefix and require signing in', function () {
    foreach (['profile.edit', 'security.edit'] as $name) {
        expect(route($name, absolute: false))->toStartWith('/admin/settings/');

        $this->get(route($name))->assertRedirect(route('login'));
    }
});

test('public registration is switched off', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});

test('the admin login page is not indexable', function () {
    $this->get(route('login'))->assertSee('noindex, nofollow', escape: false);
});

test('an administrator can be created from the console', function () {
    $this->artisan('resort:make-admin', [
        '--name' => 'Eugine',
        '--email' => 'admin@jehovens.test',
        '--password' => 'correct-horse-battery-staple',
    ])->assertSuccessful();

    $admin = User::where('email', 'admin@jehovens.test')->sole();

    expect($admin->name)->toBe('Eugine')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('correct-horse-battery-staple', $admin->password))->toBeTrue();
});

test('the console refuses a duplicate email', function () {
    User::factory()->create(['email' => 'taken@jehovens.test']);

    $this->artisan('resort:make-admin', [
        '--name' => 'Someone',
        '--email' => 'taken@jehovens.test',
        '--password' => 'correct-horse-battery-staple',
    ])->assertFailed();

    expect(User::where('email', 'taken@jehovens.test')->count())->toBe(1);
});

test('the console refuses a weak password', function () {
    $this->artisan('resort:make-admin', [
        '--name' => 'Someone',
        '--email' => 'weak@jehovens.test',
        '--password' => 'short',
    ])->assertFailed();

    expect(User::where('email', 'weak@jehovens.test')->exists())->toBeFalse();
});

test('an admin who signs in lands on the dashboard', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticated();
});
