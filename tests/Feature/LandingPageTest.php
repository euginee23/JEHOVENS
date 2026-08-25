<?php

use App\Models\User;

test('the landing page renders the marketing home view', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertViewIs('pages::marketing.home');
    $response->assertSee('Jehoven\'s Garden Resort');
});

test('the landing page exposes an anchor for every nav link', function () {
    $response = $this->get(route('home'));

    foreach (['function-hall', 'rooms', 'catering', 'about', 'services'] as $anchor) {
        $response->assertSee('id="'.$anchor.'"', escape: false);
    }
});

test('the landing page renders every hero photo', function () {
    $response = $this->get(route('home'));

    foreach (range(1, 5) as $number) {
        $response->assertSee('images/resort/hero-slider-'.$number.'.jpg', escape: false);
    }
});

test('guests are pointed at login and registration', function () {
    $response = $this->get(route('home'));

    $response->assertSee(route('login'), escape: false);
    $response->assertSee(route('register'), escape: false);
});

test('authenticated users are pointed at the dashboard instead', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee(route('dashboard'), escape: false);
    $response->assertDontSee(route('register'), escape: false);
});
