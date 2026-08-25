@php
    $pageTitle = filled($title ?? null) ? $title.' - '.config('app.name') : config('app.name');
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $pageTitle }}</title>

{{-- Staff-only pages should never be indexed. --}}
<meta name="robots" content="noindex, nofollow" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
