{{-- Public/guest shell. Deliberately light-only: no `dark` class and no @fluxAppearance,
     so a persisted dark preference from the app never leaks onto the marketing site. --}}
@props([
    'title' => null,
    'description' => null,
    // The homepage opens on a full-bleed photo, so the nav floats over it. Everywhere
    // else the page starts on a solid ground and the nav needs its own.
    'transparentNav' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.marketing-head', ['title' => $title, 'description' => $description])
    </head>
    <body class="min-h-screen bg-sand-50 text-brand-950 antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-brand-800 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
            {{ __('Skip to content') }}
        </a>

        <x-marketing.nav :transparent="$transparentNav" />

        <main id="main">
            {{ $slot }}
        </main>

        <x-marketing.footer />

        @fluxScripts
    </body>
</html>
