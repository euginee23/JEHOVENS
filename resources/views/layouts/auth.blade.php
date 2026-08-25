{{-- Admin sign-in shell. Deliberately light-only and on the resort palette, so the staff
     area reads as part of the same site rather than the Laravel starter kit. --}}
@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.auth-head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
        <div class="relative isolate flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12 sm:px-6">
            <x-marketing.glow />

            <div class="relative w-full max-w-md">
                <a href="{{ route('home') }}" class="flex items-center justify-center gap-2.5">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500 shadow-sm shadow-brand-600/30">
                        <x-marketing.logo-mark class="size-5.5 text-white" />
                    </span>
                    <span class="text-lg font-bold tracking-tight text-zinc-900">{{ config('app.name') }}</span>
                </a>

                <div class="mt-8 rounded-3xl border border-zinc-200 bg-white p-8 shadow-xl shadow-brand-900/5 sm:p-10">
                    {{ $slot }}
                </div>

                <p class="mt-8 text-center text-sm">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 text-zinc-500 transition-colors hover:text-brand-700">
                        <span aria-hidden="true">&larr;</span>
                        {{ __('Back to the website') }}
                    </a>
                </p>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
