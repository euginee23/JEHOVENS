{{-- Self-contained error shell.

     Deliberately does NOT use partials/marketing-head, the marketing nav or footer,
     Livewire, or Flux: an error page has to render when the application is unhealthy, and
     a 500 page that itself throws is worse than no custom page at all.

     The @vite call is guarded because a fresh clone with no built assets would otherwise
     raise a ViteException *from the error page*, and links use url() rather than route()
     so a routing fault cannot cascade. --}}
@props([
    'code',
    'title',
    'message' => null,
])

@php
    $assetsBuilt = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="robots" content="noindex" />

        <title>{{ $code }} &middot; {{ $title }} - {{ config('app.name', 'Jehoven\'s Garden Resort') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">

        @if ($assetsBuilt)
            @vite(['resources/css/app.css'])
        @else
            {{-- Fallback so the page is still legible before the first `npm run build`. --}}
            <style>
                body {
                    margin: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: ui-sans-serif, system-ui, sans-serif;
                    color: #18181b;
                    background: #fff;
                    text-align: center;
                    padding: 2rem;
                }
                a { color: #0d8f9e; }
            </style>
        @endif
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">
        <div class="relative isolate flex min-h-screen flex-col overflow-hidden">
            {{-- Backdrop --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -left-32 -top-40 size-[36rem] rounded-full bg-brand-300/30 blur-3xl"></div>
                <div class="absolute -right-24 -top-24 size-[30rem] rounded-full bg-coral-300/20 blur-3xl"></div>
            </div>

            <header class="relative px-4 py-6 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500 shadow-sm shadow-brand-600/30">
                        <x-marketing.logo-mark class="size-5 text-white" />
                    </span>
                    <span class="text-lg font-bold tracking-tight text-zinc-900">{{ config('app.name', 'Jehoven\'s Garden Resort') }}</span>
                </a>
            </header>

            <main class="relative flex flex-1 items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
                <div class="w-full max-w-lg text-center">
                    <p class="text-7xl font-bold tracking-tight sm:text-8xl">
                        <span class="text-gradient">{{ $code }}</span>
                    </p>

                    <h1 class="mt-4 text-2xl font-bold tracking-tight text-balance text-zinc-900 sm:text-3xl">
                        {{ $title }}
                    </h1>

                    @if ($message)
                        <p class="mx-auto mt-4 max-w-md text-base/7 text-pretty text-zinc-600">
                            {{ $message }}
                        </p>
                    @endif

                    @isset($actions)
                        <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                            {{ $actions }}
                        </div>
                    @endisset

                    {{ $slot }}
                </div>
            </main>

            <footer class="relative px-4 py-8 text-center sm:px-6 lg:px-8">
                <p class="text-sm text-zinc-500">
                    &copy; {{ now()->year }} {{ config('app.name', 'Jehoven\'s Garden Resort') }}
                </p>
            </footer>
        </div>
    </body>
</html>
