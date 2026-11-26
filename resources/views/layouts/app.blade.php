<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101820">
    @if (request()->is('admin', 'admin/*'))
        <meta name="robots" content="noindex, nofollow">
        <meta name="description" content="FleetGuard administration">
        <title>{{ trim($__env->yieldContent('title', 'FleetGuard Administration')) }}</title>
    @else
        @php
            $metaTitle = trim($__env->yieldContent('title', 'FleetGuard | Premium Car Rental'));
            $metaDescription = trim($__env->yieldContent('meta-description', 'Explore FleetGuard vehicles, check availability for your dates, and send a booking request for review in Panabo City.'));
            $canonicalUrl = url()->current();
            $isIndexablePage = request()->routeIs('home', 'fleet', 'fleet.show');
            $organizationSchema = null;
            $breadcrumbItems = [];

            if (request()->routeIs('home') && filled(config('fleetguard.name'))) {
                $organizationSchema = array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => config('fleetguard.name'),
                    'url' => route('home'),
                    'telephone' => filled(config('fleetguard.phone')) ? config('fleetguard.phone') : null,
                    'email' => filled(config('fleetguard.email')) ? config('fleetguard.email') : null,
                    'address' => filled(config('fleetguard.address')) ? config('fleetguard.address') : null,
                ], fn ($value) => $value !== null);
            }

            if (request()->routeIs('fleet')) {
                $breadcrumbItems = [
                    ['name' => 'Home', 'item' => route('home')],
                    ['name' => 'Fleet', 'item' => route('fleet')],
                ];
            } elseif (request()->routeIs('fleet.show') && isset($vehicle)) {
                $breadcrumbItems = [
                    ['name' => 'Home', 'item' => route('home')],
                    ['name' => 'Fleet', 'item' => route('fleet')],
                    ['name' => $vehicle->name, 'item' => route('fleet.show', $vehicle)],
                ];
            }

            $breadcrumbSchema = $breadcrumbItems ? [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(
                    fn ($item, $index) => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'name' => $item['name'],
                        'item' => $item['item'],
                    ],
                    $breadcrumbItems,
                    array_keys($breadcrumbItems),
                ),
            ] : null;
        @endphp
        <meta name="robots" content="{{ $isIndexablePage ? 'index, follow' : 'noindex, follow' }}">
        <meta name="description" content="{{ $metaDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('fleetguard.name', 'FleetGuard') }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        @if (request()->routeIs('fleet.show') && isset($vehicle) && $vehicle->image_path && is_file(public_path($vehicle->image_path)))
            <meta property="og:image" content="{{ asset($vehicle->image_path) }}">
        @endif
        @if ($organizationSchema)
            <script type="application/ld+json">@json($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
        @endif
        @if ($breadcrumbSchema)
            <script type="application/ld+json">@json($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
        @endif
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
