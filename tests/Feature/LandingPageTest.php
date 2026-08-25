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

test('the rooms card shows real room photos, not the pool', function () {
    $response = $this->get(route('home'));

    foreach (['room-1.jpg', 'room-2.jpg', 'room-3.jpg'] as $photo) {
        $response->assertSee('images/rooms/'.$photo, escape: false);
    }
});

test('the function hall card shows the actual hall', function () {
    $this->get(route('home'))->assertSee('images/function-hall/function-hall-1.jpg', escape: false);
});

test('every photo referenced by the landing page exists on disk', function () {
    $html = $this->get(route('home'))->getContent();

    preg_match_all('#/images/([\w-]+/[\w.-]+\.jpg)#', $html, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach (array_unique($matches[1]) as $path) {
        expect(public_path('images/'.$path))->toBeFile();
    }
});

test('the catering card shows a real catering photo, not the pool', function () {
    $this->get(route('home'))->assertSee('images/catering/catering-1.jpg', escape: false);
});

/**
 * The bug being fixed: the Rooms and Catering cards both showed a swimming pool, so the
 * three offer cards were indistinguishable. Those two shots now belong to the hero only.
 */
test('the offer cards no longer reuse the pool photos', function () {
    $html = $this->get(route('home'))->getContent();

    $cards = str($html)->after('id="function-hall"')->before('</section>')->toString();

    expect($cards)->not->toContain('hero-slider-5.jpg')
        ->and($cards)->not->toContain('hero-slider-3.jpg');
});

test('a card with several photos crossfades, a card with one does not', function () {
    $html = $this->get(route('home'))->getContent();

    $rooms = str($html)->after('id="rooms"')->before('id="catering"')->toString();
    $catering = str($html)->after('id="catering"')->before('</section>')->toString();

    expect($rooms)->toContain('x-data')
        ->and($rooms)->toContain('setInterval')
        ->and($catering)->not->toContain('x-data')
        ->and($catering)->not->toContain('setInterval');
});

test('the first photo of a slideshow is visible before Alpine boots', function () {
    $html = $this->get(route('home'))->getContent();

    $rooms = str($html)->after('id="rooms"')->before('id="catering"')->toString();

    // Count the static class attribute only — `opacity-100` also appears inside each
    // image's x-bind:class expression, which says nothing about the pre-Alpine state.
    $opaque = preg_match_all('/(?<![-:\w])class="[^"]*\bopacity-100\b/', $rooms);
    $transparent = preg_match_all('/(?<![-:\w])class="[^"]*\bopacity-0\b/', $rooms);

    // Exactly one frame renders opaque server-side; the other two start transparent.
    expect($opaque)->toBe(1)->and($transparent)->toBe(2);
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
