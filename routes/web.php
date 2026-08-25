<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site — guests only, no accounts required
|--------------------------------------------------------------------------
*/

Route::view('/', 'pages::marketing.home')->name('home');

Route::livewire('book/function-hall', 'pages::booking.function-hall')->name('booking.function-hall');
Route::livewire('book/rooms', 'pages::booking.rooms')->name('booking.rooms');
Route::livewire('book/catering', 'pages::booking.catering')->name('booking.catering');

/*
|--------------------------------------------------------------------------
| Admin area — the only part of the site that needs an account
|--------------------------------------------------------------------------
|
| Fortify serves its own routes under the same `admin` prefix (see the
| `prefix` key in config/fortify.php), so a guest hitting /admin is sent to
| /admin/login and returned here once they sign in.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('admin', 'pages::admin.dashboard')->name('admin.dashboard');

    Route::livewire('admin/function-halls', 'pages::admin.function-halls')->name('admin.function-halls');
    Route::livewire('admin/rooms', 'pages::admin.rooms')->name('admin.rooms');
    Route::livewire('admin/catering', 'pages::admin.catering')->name('admin.catering');
    Route::livewire('admin/bookings', 'pages::admin.bookings')->name('admin.bookings');
});

/*
|--------------------------------------------------------------------------
| Legacy redirects
|--------------------------------------------------------------------------
*/

Route::redirect('dashboard', 'admin');
// Leading slash matters: a relative destination resolves against the source path, which
// would send this to /admin/function-halls/admin/bookings.
Route::redirect('admin/function-halls/bookings', '/admin/bookings?type=halls');
Route::redirect('settings', 'admin/settings');

require __DIR__.'/settings.php';
