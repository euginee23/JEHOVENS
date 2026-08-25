{{-- Public/guest shell. Deliberately light-only: no `dark` class and no @fluxAppearance,
     so a persisted dark preference from the app never leaks onto the marketing site. --}}
@props([
    'title' => null,
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.marketing-head', ['title' => $title, 'description' => $description])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
            {{ __('Skip to content') }}
        </a>

        <x-marketing.nav />

        <main id="main">
            {{ $slot }}
        </main>

        <x-marketing.footer />

        @fluxScripts
    </body>
</html>
