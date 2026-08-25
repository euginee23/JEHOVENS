<x-layouts::auth :title="__('Staff sign in')">
    <div class="flex flex-col gap-6">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-700">
                {{ __('Staff only') }}
            </span>

            <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-900">{{ __('Sign in to the admin area') }}</h1>

            <p class="mt-2 text-sm/6 text-zinc-600">
                {{ __('Manage halls, rooms, catering, and incoming bookings. Guests do not need an account to book.') }}
            </p>
        </div>

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="you@jehovens.com"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Your password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <a
                        href="{{ route('password.request') }}"
                        class="absolute end-0 top-0 text-sm font-medium text-brand-600 transition-colors hover:text-brand-700"
                    >
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>

            <flux:checkbox name="remember" :label="__('Keep me signed in')" :checked="old('remember')" />

            <button
                type="submit"
                data-test="login-button"
                class="w-full rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:bg-brand-700"
            >
                {{ __('Sign in') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
