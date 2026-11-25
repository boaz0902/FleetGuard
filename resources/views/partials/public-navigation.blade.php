@php($onHomePage = request()->routeIs('home'))

<header class="site-header{{ $onHomePage ? '' : ' is-solid' }}" data-site-header>
    <div class="nav-shell">
        <a class="brand" href="{{ route('home') }}" aria-label="FleetGuard home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 40 40" fill="none">
                    <path d="M20 3.5 34 8.7v10.8c0 8.4-5.7 14.2-14 17-8.3-2.8-14-8.6-14-17V8.7L20 3.5Z" stroke="currentColor" stroke-width="1.5"/>
                    <path d="m11.5 22.5 1.7-5.2c.3-.9 1.1-1.5 2-1.5h9.6c.9 0 1.7.6 2 1.5l1.7 5.2v4h-2v-2H13.5v2h-2v-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M13.8 21h12.4M15 24h.1m9.8 0h.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="brand-wordmark">{{ strtoupper(config('fleetguard.name', 'FleetGuard')) }}</span>
        </a>

        <button class="menu-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="primary-navigation" data-menu-toggle>
            <i class="bi bi-list" aria-hidden="true"></i>
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>

        <nav class="primary-navigation" id="primary-navigation" aria-label="Main navigation" data-navigation>
            <div class="nav-primary-links">
                <a class="nav-link" href="{{ route('fleet') }}" data-section-link>
                    <i class="nav-icon bi bi-car-front" aria-hidden="true"></i><span>Our Fleet</span>
                </a>
                <a class="nav-link" href="{{ $onHomePage ? '#how-it-works' : route('home').'#how-it-works' }}" data-section-link>
                    <i class="nav-icon bi bi-signpost-split" aria-hidden="true"></i><span>How It Works</span>
                </a>
                <a class="nav-link" href="{{ $onHomePage ? '#about' : route('home').'#about' }}" data-section-link>
                    <i class="nav-icon bi bi-shield-check" aria-hidden="true"></i><span>About</span>
                </a>
                <a class="nav-link" href="{{ $onHomePage ? '#faq' : route('home').'#faq' }}" data-section-link>
                    <i class="nav-icon bi bi-question-circle" aria-hidden="true"></i><span>FAQ</span>
                </a>
                <a class="nav-link" href="{{ $onHomePage ? '#contact' : route('home').'#contact' }}" data-section-link>
                    <i class="nav-icon bi bi-telephone" aria-hidden="true"></i><span>Contact</span>
                </a>
            </div>

            <div class="nav-account-links" data-account-menu>
                @auth
                    <button class="account-menu-toggle" type="button" aria-expanded="false" aria-controls="account-menu-panel" aria-label="Open account menu" data-account-toggle>
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span>{{ auth()->user()->isCustomer() ? 'My account' : 'Account' }}</span>
                        <i class="bi bi-chevron-down account-menu-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="account-menu-panel" id="account-menu-panel" data-account-panel hidden>
                        @if (auth()->user()->isCustomer())
                            <a class="account-menu-link" href="{{ route('account') }}">
                                <i class="bi bi-person-circle" aria-hidden="true"></i><span>My account</span>
                            </a>
                            <a class="account-menu-link" href="{{ route('account.profile') }}">
                                <i class="bi bi-person-gear" aria-hidden="true"></i><span>Profile</span>
                            </a>
                            @if (auth()->user()->hasVerifiedEmail())
                                <a class="account-menu-link" href="{{ route('account.bookings') }}">
                                    <i class="bi bi-calendar2-check" aria-hidden="true"></i><span>My Bookings</span>
                                </a>
                            @endif
                            <form class="public-logout-form" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="account-menu-link" type="submit">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Sign out</span>
                                </button>
                            </form>
                        @elseif (auth()->user()->isAdmin() || auth()->user()->isManager())
                            <form class="public-logout-form" method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button class="account-menu-link" type="submit">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Sign out</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @else
                    <a class="nav-link" href="{{ route('login') }}">
                        <i class="nav-icon bi bi-box-arrow-in-right" aria-hidden="true"></i><span>Sign in</span>
                    </a>
                    <a class="nav-link" href="{{ route('register') }}">
                        <i class="nav-icon bi bi-person-circle" aria-hidden="true"></i><span>Create account</span>
                    </a>
                @endauth
            </div>

            <div class="nav-mobile-account-links" aria-label="Account">
                @auth
                    @if (auth()->user()->isCustomer())
                        <a class="nav-link" href="{{ route('account') }}">
                            <i class="nav-icon bi bi-person-circle" aria-hidden="true"></i><span>My account</span>
                        </a>
                        <a class="nav-link" href="{{ route('account.profile') }}">
                            <i class="nav-icon bi bi-person-gear" aria-hidden="true"></i><span>Profile</span>
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
                        <form class="public-logout-form" method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button class="nav-logout" type="submit">
                                <i class="nav-icon bi bi-box-arrow-right" aria-hidden="true"></i><span>Sign out</span>
                            </button>
                        </form>
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

            <a class="nav-cta" href="{{ route('booking') }}">
                <span>Book a Vehicle</span><i class="bi bi-arrow-up-right nav-arrow" aria-hidden="true"></i>
            </a>
        </nav>
    </div>
</header>
