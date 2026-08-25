@props([
    'title',
    'description',
])

<div class="flex w-full flex-col">
    <h1 class="text-2xl font-bold tracking-tight text-zinc-900">{{ $title }}</h1>
    <p class="mt-2 text-sm/6 text-zinc-600">{{ $description }}</p>
</div>
