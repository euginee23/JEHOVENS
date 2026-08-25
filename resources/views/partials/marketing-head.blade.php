@php
    $pageTitle = filled($title ?? null) ? $title.' - '.config('app.name') : config('app.name').' - '.__('Relax, unwind, and celebrate');
    $pageDescription = $description ?? __('Jehoven\'s Garden Resort offers function halls, catering, flexible room stays, and adult & kids pools — reserve any of them with a simple 50% down payment.');
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $pageTitle }}</title>

<meta name="description" content="{{ $pageDescription }}" />

<meta property="og:type" content="website" />
<meta property="og:site_name" content="{{ config('app.name') }}" />
<meta property="og:title" content="{{ $pageTitle }}" />
<meta property="og:description" content="{{ $pageDescription }}" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta property="og:image" content="{{ asset('images/resort/hero-slider-1.jpg') }}" />

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $pageTitle }}" />
<meta name="twitter:description" content="{{ $pageDescription }}" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
