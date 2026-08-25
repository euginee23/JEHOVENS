{{-- Links use url() rather than route() so a routing fault cannot cascade into the error
     page itself. --}}
<x-layouts::error
    code="404"
    :title="__('We can\'t find that page')"
    :message="__('The page you were after has moved or never existed. Everything you can book is still here, though.')"
>
    <x-slot:actions>
        <x-error-action :href="url('/')">{{ __('Back to home') }}</x-error-action>
        <x-error-action :href="url('/#book')" variant="secondary">{{ __('See what you can book') }}</x-error-action>
    </x-slot:actions>

    <div class="mt-12 border-t border-zinc-200 pt-8">
        <p class="text-sm font-semibold text-zinc-900">{{ __('Or go straight to a booking') }}</p>

        <ul class="mt-4 flex flex-wrap items-center justify-center gap-2">
            @foreach ([
                ['label' => __('Function hall'), 'href' => url('/book/function-hall')],
                ['label' => __('Rooms'), 'href' => url('/book/rooms')],
                ['label' => __('Catering'), 'href' => url('/book/catering')],
            ] as $link)
                <li>
                    <a
                        href="{{ $link['href'] }}"
                        class="rounded-full border border-zinc-200 bg-white px-3.5 py-1.5 text-sm text-zinc-600 transition-colors hover:border-brand-300 hover:text-brand-700"
                    >
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts::error>
