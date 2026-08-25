<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Hash;

test('the seeder creates a verified administrator with the configured credentials', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::sole();

    expect($admin->email)->toBe(config('resort.admin.email'))
        ->and($admin->name)->toBe(config('resort.admin.name'))
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check(config('resort.admin.password'), $admin->password))->toBeTrue();
});

test('the seeded administrator can sign in and reach the admin area', function () {
    $this->seed(AdminSeeder::class);

    $this->post(route('login.store'), [
        'email' => config('resort.admin.email'),
        'password' => config('resort.admin.password'),
    ])->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticated();

    $this->get('/admin')->assertOk();
});

test('re-seeding does not create a second administrator', function () {
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    expect(User::where('email', config('resort.admin.email'))->count())->toBe(1);
});

test('the credentials can be overridden from configuration', function () {
    config([
        'resort.admin.name' => 'Resort Owner',
        'resort.admin.email' => 'owner@jehovens.test',
        'resort.admin.password' => 'a-much-better-password',
    ]);

    $this->seed(AdminSeeder::class);

    $admin = User::sole();

    expect($admin->email)->toBe('owner@jehovens.test')
        ->and($admin->name)->toBe('Resort Owner')
        ->and(Hash::check('a-much-better-password', $admin->password))->toBeTrue();
});

test('the default seeder run leaves no stray test user behind', function () {
    $this->seed();

    expect(User::pluck('email')->all())->toBe([config('resort.admin.email')]);
});
