@php
    $explore = [
        ['label' => __('Function Hall'), 'href' => route('booking.function-hall')],
        ['label' => __('Rooms'), 'href' => route('booking.rooms')],
        ['label' => __('Catering'), 'href' => route('home').'#catering'],
        ['label' => __('About the resort'), 'href' => route('home').'#about'],
    ];
@endphp

<footer class="border-t border-zinc-200 bg-zinc-50">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4 lg:gap-8">
            <div class="lg:col-span-2">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-linear-to-br from-brand-600 to-brand-500 shadow-sm shadow-brand-600/30">
                        <x-marketing.logo-mark class="size-5 text-white" />
                    </span>
                    <span class="text-lg font-bold tracking-tight text-zinc-900">{{ config('app.name') }}</span>
                </a>

                <p class="mt-4 max-w-sm text-sm/6 text-zinc-600">
                    {{ __('A relaxing place for leisure and celebrations — function halls, catering, comfortable rooms, and pools for adults and kids, all reservable with a 50% down payment.') }}
                </p>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-zinc-900">{{ __('Explore') }}</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($explore as $link)
                        <li>
                            <a href="{{ $link['href'] }}" class="text-sm text-zinc-600 transition-colors hover:text-brand-700">
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-zinc-900">{{ __('Reservations') }}</h2>
                <ul class="mt-4 space-y-3">
                    @auth
                        <li>
                            <a href="{{ route('dashboard') }}" class="text-sm text-zinc-600 transition-colors hover:text-brand-700">
                                {{ __('Go to dashboard') }}
                            </a>
                        </li>
                    @else
                        <li>
                            <a href="{{ route('register') }}" class="text-sm text-zinc-600 transition-colors hover:text-brand-700">
                                {{ __('Create an account') }}
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('login') }}" class="text-sm text-zinc-600 transition-colors hover:text-brand-700">
                                {{ __('Log in') }}
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>

        <div class="mt-12 border-t border-zinc-200 pt-8">
            <p class="text-sm text-zinc-500">
                &copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </div>
</footer>
