<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101820">
    @if (request()->is('admin', 'admin/*'))
        <meta name="description" content="FleetGuard administration">
        <title>{{ trim($__env->yieldContent('title', 'FleetGuard Administration')) }}</title>
    @else
        @php
            $metaTitle = trim($__env->yieldContent('title', 'FleetGuard | Premium Car Rental'));
            $metaDescription = trim($__env->yieldContent('meta-description', 'Explore FleetGuard vehicles, check availability for your dates, and send a booking request for review in Panabo City.'));
        @endphp
        <meta name="description" content="{{ $metaDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('fleetguard.name', 'FleetGuard') }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <title>{{ $metaTitle }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="https://fonts.gstatic.com/s/spacegrotesk/v22/V8mDoQDjQSkFtoMM3T6r8E7mPbF4Cw.woff2" as="font" type="font/woff2" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&family=Space+Grotesk:wght@400..700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    @yield('content')

    @unless (request()->is('admin', 'admin/*'))
        @include('partials.public-footer')
    @endunless
</body>
</html>
