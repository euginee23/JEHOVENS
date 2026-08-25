<?php

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The five codes that have a branded page.
 *
 * @return array<int, array<int, string>>
 */
dataset('error codes', [['403'], ['404'], ['419'], ['500'], ['503']]);

test('a missing url renders the branded 404, not Laravel\'s default', function () {
    $response = $this->get('/a-page-that-does-not-exist');

    $response->assertNotFound()
        ->assertSee('We can\'t find that page')
        ->assertSee('Garden Resort')
        ->assertDontSee('Not Found | Laravel', escape: false);
});

test('the 404 page offers the three booking pages', function () {
    $html = $this->get('/nope')->getContent();

    foreach (['/book/function-hall', '/book/rooms', '/book/catering'] as $path) {
        expect($html)->toContain($path);
    }
});

test('each error view renders on its own', function (string $code) {
    $html = view("errors.{$code}", [
        'exception' => new HttpException((int) $code),
    ])->render();

    expect($html)->toContain($code)
        ->and($html)->toContain('<!DOCTYPE html>')
        ->and($html)->toContain('Garden Resort');
})->with('error codes');

test('every error page asks not to be indexed', function (string $code) {
    $html = view("errors.{$code}", [
        'exception' => new HttpException((int) $code),
    ])->render();

    expect($html)->toContain('name="robots" content="noindex"');
})->with('error codes');

/**
 * The whole point of the shell: it must render when the app is unhealthy. An unguarded
 *
 * @vite would throw a ViteException from the error page itself on a clone that has never
 * been built.
 */
test('every error page still renders when the assets have not been built', function (string $code) {
    // Hide both signals the shell checks, so the test behaves the same whether or not a
    // Vite dev server happens to be running on this machine.
    $hidden = [];

    foreach ([public_path('build/manifest.json'), public_path('hot')] as $path) {
        if (file_exists($path)) {
            rename($path, $path.'.testing-backup');
            $hidden[] = $path;
        }
    }

    try {
        $html = view("errors.{$code}", [
            'exception' => new HttpException((int) $code),
        ])->render();

        expect($html)->toContain($code)
            ->and($html)->toContain('<!DOCTYPE html>')
            // The inline fallback stands in for the stylesheet.
            ->and($html)->not->toContain('/build/assets/')
            ->and($html)->toContain('font-family: ui-sans-serif');
    } finally {
        foreach ($hidden as $path) {
            rename($path.'.testing-backup', $path);
        }
    }
})->with('error codes');

/**
 * Blade comments are stripped first: these files explain in prose *why* they avoid
 * route(), and the check is about the code, not the commentary.
 */
function bladeCode(string $path): string
{
    return (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($path));
}

test('error pages avoid route() so a routing fault cannot cascade', function (string $code) {
    expect(bladeCode(resource_path("views/errors/{$code}.blade.php")))->not->toContain('route(');
})->with('error codes');

test('the error shell pulls in nothing that needs a healthy app', function () {
    $source = bladeCode(resource_path('views/layouts/error.blade.php'));

    foreach (['@fluxScripts', '@fluxAppearance', 'partials.marketing-head', 'x-marketing.nav', 'x-marketing.footer', 'auth()->user()', 'route('] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }
});

test('an aborted 403 renders the branded page', function () {
    Route::get('/__test-forbidden', fn () => abort(403));

    $this->get('/__test-forbidden')
        ->assertForbidden()
        ->assertSee('That area is staff only');
});

test('guests hitting the admin area are still redirected, not shown a 403', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});
