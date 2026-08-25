<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    Route::middleware(['auth'])->group(function () {
        // Leading slash matters: inside a prefix group the destination is prefixed too,
        // which would send this to /admin/admin/settings/profile.
        Route::redirect('settings', '/admin/settings/profile');

        Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

        Route::livewire('settings/security', 'pages::settings.security')
            ->middleware([
                'password.confirm',
            ])
            ->name('security.edit');
    });
});
