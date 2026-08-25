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

test('the public site points guests at the booking pages, not at an account', function () {
    $response = $this->get(route('home'));

    $response->assertSee(route('booking.function-hall'), escape: false);
    $response->assertSee(route('booking.rooms'), escape: false);
    $response->assertSee(route('booking.catering'), escape: false);

    $response->assertDontSee(route('login'), escape: false);
    $response->assertDontSee(route('admin.dashboard'), escape: false);
});

test('the public site never links to the admin area, even for a signed-in admin', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee(route('login'), escape: false);
    $response->assertDontSee(route('admin.dashboard'), escape: false);
});
