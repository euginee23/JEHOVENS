<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages::marketing.home')->name('home');

Route::livewire('book/function-hall', 'pages::booking.function-hall')->name('booking.function-hall');
Route::livewire('book/rooms', 'pages::booking.rooms')->name('booking.rooms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
