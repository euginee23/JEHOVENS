{{-- Staff shell. Light-only and on the resort palette, with the navigation inside the page
     rather than docked to the window edge. No `dark` class and no @fluxAppearance. --}}
@props([
    'title' => null,
])

@php
    $links = [
        ['label' => __('Dashboard'), 'route' => 'admin.dashboard'],
        ['label' => __('Function halls'), 'route' => 'admin.function-halls'],
        ['label' => __('Rooms'), 'route' => 'admin.rooms'],
        ['label' => __('Catering'), 'route' => 'admin.catering'],
        ['label' => __('Bookings'), 'route' => 'admin.bookings'],
        ['label' => __('Settings'), 'route' => 'profile.edit'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.admin-head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
        <header x-data="{ open: false }" class="relative border-b border-zinc-200 bg-white">
            <nav class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:gap-6 sm:px-6 lg:px-8" aria-label="{{ __('Admin') }}">
                <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-2.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500 shadow-sm shadow-brand-600/30">
                        <x-marketing.logo-mark class="size-5 text-white" />
                    </span>
                    <span class="truncate font-bold tracking-tight text-zinc-900 sm:text-lg">{{ config('app.name') }}</span>
                </a>

                <div class="hidden items-center gap-1 lg:flex">
                    @foreach ($links as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            @class([
                                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                'bg-brand-50 text-brand-700' => request()->routeIs($link['route']),
                                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900' => ! request()->routeIs($link['route']),
                            ])
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="ms-auto flex shrink-0 items-center gap-2">
                    <a
                        href="{{ route('home') }}"
                        class="hidden rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 sm:block"
                    >
                        {{ __('View site') }}
                    </a>

                    <flux:dropdown position="bottom" align="end" class="max-lg:hidden">
                        <flux:profile
                            :name="auth()->user()->name"
                            :initials="auth()->user()->initials()"
                            icon-trailing="chevron-down"
                        />

                        <flux:menu>
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>

                            <flux:menu.separator />

                            <flux:menu.item :href="route('profile.edit')" icon="cog">
                                {{ __('Settings') }}
                            </flux:menu.item>

                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <flux:menu.item
                                    as="button"
                                    type="submit"
                                    icon="arrow-right-start-on-rectangle"
                                    class="w-full cursor-pointer"
                                    data-test="logout-button"
                                >
                                    {{ __('Log out') }}
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>

                    <button
                        type="button"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open ? 'true' : 'false'"
                        aria-controls="admin-nav"
                        class="-me-1 flex size-10 items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900 lg:hidden"
                    >
                        <span class="sr-only">{{ __('Toggle navigation') }}</span>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </nav>

            {{-- Overlays the page instead of sitting in the flow, so opening it never
                 shifts the content underneath. --}}
            <div
                id="admin-nav"
                x-show="open"
                x-cloak
                x-transition.origin.top
                x-on:click.outside="open = false"
                class="absolute inset-x-0 top-full z-40 border-t border-zinc-200 bg-white shadow-lg shadow-zinc-900/5 lg:hidden"
            >
                <div class="space-y-1 px-4 py-4 sm:px-6">
                    @foreach ($links as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            @class([
                                'block rounded-lg px-3 py-2.5 text-base font-medium',
                                'bg-brand-50 text-brand-700' => request()->routeIs($link['route']),
                                'text-zinc-700 hover:bg-zinc-100' => ! request()->routeIs($link['route']),
                            ])
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach

                    <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-zinc-700 hover:bg-zinc-100">
                        {{ __('View site') }}
                    </a>

                    <div class="mt-3 border-t border-zinc-200 pt-3">
                        <p class="px-3 text-sm font-medium text-zinc-900">{{ auth()->user()->name }}</p>
                        <p class="px-3 text-sm text-zinc-500">{{ auth()->user()->email }}</p>

                        <form method="POST" action="{{ route('logout') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="block w-full rounded-lg px-3 py-2.5 text-start text-base font-medium text-zinc-700 hover:bg-zinc-100">
                                {{ __('Log out') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
