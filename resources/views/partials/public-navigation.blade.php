@php($onHomePage = request()->routeIs('home'))

<header class="site-header" data-site-header>
    <div class="nav-shell">
        <a class="brand" href="{{ route('home') }}" aria-label="FleetGuard home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 40 40" fill="none">
                    <path d="M20 3.5 34 8.7v10.8c0 8.4-5.7 14.2-14 17-8.3-2.8-14-8.6-14-17V8.7L20 3.5Z" stroke="currentColor" stroke-width="1.5"/>
                    <path d="m11.5 22.5 1.7-5.2c.3-.9 1.1-1.5 2-1.5h9.6c.9 0 1.7.6 2 1.5l1.7 5.2v4h-2v-2H13.5v2h-2v-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M13.8 21h12.4M15 24h.1m9.8 0h.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="brand-wordmark">FLEET<span>GUARD</span></span>
        </a>

        <button class="menu-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="primary-navigation" data-menu-toggle>
            <i class="bi bi-list" aria-hidden="true"></i>
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>

        <nav class="primary-navigation" id="primary-navigation" aria-label="Main navigation" data-navigation>
            <a href="{{ $onHomePage ? '#fleet' : route('home').'#fleet' }}" data-public-nav-link>Our Fleet</a>
            <a href="{{ $onHomePage ? '#experience' : route('home').'#experience' }}" data-public-nav-link>How It Works</a>
            <a href="{{ $onHomePage ? '#experience' : route('home').'#experience' }}" data-public-nav-link>About</a>
            <a href="{{ $onHomePage ? '#experience' : route('home').'#experience' }}" data-public-nav-link>Contact</a>

            @auth
                @if (auth()->user()->isCustomer())
                    <a href="{{ route('account') }}">My account</a>
                    <form class="public-logout-form" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="public-logout" type="submit">Sign out</button>
                    </form>
                @elseif (auth()->user()->isAdmin() || auth()->user()->isManager())
                    <a href="{{ route('admin.dashboard') }}">Admin portal</a>
                @endif
            @else
                <a href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('register') }}">Create account</a>
            @endauth

            <a class="nav-cta" href="{{ $onHomePage ? '#booking' : route('home').'#booking' }}" data-public-nav-link>
                Book a Vehicle <i class="bi bi-arrow-up-right nav-arrow" aria-hidden="true"></i>
            </a>
        </nav>
    </div>
</header>
