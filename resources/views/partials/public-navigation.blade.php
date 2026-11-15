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
            <div class="nav-primary-links">
                <a class="nav-link" href="{{ $onHomePage ? '#fleet' : route('home').'#fleet' }}" data-public-nav-link>
                    <i class="nav-icon bi bi-car-front" aria-hidden="true"></i><span>Our Fleet</span>
                </a>
                <a class="nav-link" href="{{ $onHomePage ? '#experience' : route('home').'#experience' }}" data-public-nav-link>
                    <i class="nav-icon bi bi-signpost-split" aria-hidden="true"></i><span>How It Works</span>
                </a>
                <a class="nav-link" href="{{ $onHomePage ? '#experience' : route('home').'#experience' }}" data-public-nav-link>
                    <i class="nav-icon bi bi-shield-check" aria-hidden="true"></i><span>About</span>
                </a>
                <a class="nav-link" href="{{ $onHomePage ? '#experience' : route('home').'#experience' }}" data-public-nav-link>
                    <i class="nav-icon bi bi-telephone" aria-hidden="true"></i><span>Contact</span>
                </a>
            </div>

            <div class="nav-account-links">
                @auth
                    @if (auth()->user()->isCustomer())
                        <a class="nav-link" href="{{ route('account') }}">
                            <i class="nav-icon bi bi-person-circle" aria-hidden="true"></i><span>My account</span>
                        </a>
                        @if (auth()->user()->hasVerifiedEmail())
                            <a class="nav-link" href="{{ route('account.bookings') }}">
                                <i class="nav-icon bi bi-calendar2-check" aria-hidden="true"></i><span>My Bookings</span>
                            </a>
                        @endif
                        <form class="public-logout-form" method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="nav-logout" type="submit">
                                <i class="nav-icon bi bi-box-arrow-right" aria-hidden="true"></i><span>Sign out</span>
                            </button>
                        </form>
                    @elseif (auth()->user()->isAdmin() || auth()->user()->isManager())
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">
                            <i class="nav-icon bi bi-person-circle" aria-hidden="true"></i><span>Admin portal</span>
                        </a>
                    @endif
                @else
                    <a class="nav-link" href="{{ route('login') }}">
                        <i class="nav-icon bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Sign in</span>
                    </a>
                    <a class="nav-link" href="{{ route('register') }}">
                        <i class="nav-icon bi bi-person-circle" aria-hidden="true"></i><span>Create account</span>
                    </a>
                @endauth
            </div>

            <a class="nav-cta" href="{{ $onHomePage ? '#booking' : route('home').'#booking' }}" data-public-nav-link>
                <span>Book a Vehicle</span><i class="bi bi-arrow-up-right nav-arrow" aria-hidden="true"></i>
            </a>
        </nav>
    </div>
</header>
