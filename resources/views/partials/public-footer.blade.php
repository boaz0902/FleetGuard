@php($onHomePage = request()->routeIs('home'))

<footer class="public-footer">
    <div class="public-footer-main">
        <div class="public-footer-brand">
            <a class="public-footer-wordmark" href="{{ route('home') }}" aria-label="FleetGuard home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none">
                        <path d="M20 3.5 34 8.7v10.8c0 8.4-5.7 14.2-14 17-8.3-2.8-14-8.6-14-17V8.7L20 3.5Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="m11.5 22.5 1.7-5.2c.3-.9 1.1-1.5 2-1.5h9.6c.9 0 1.7.6 2 1.5l1.7 5.2v4h-2v-2H13.5v2h-2v-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M13.8 21h12.4M15 24h.1m9.8 0h.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="brand-wordmark">FLEET<span>GUARD</span></span>
            </a>
            <p>{{ config('fleetguard.tagline') ?: config('fleetguard.tagline_default', 'A considered car rental experience in Panabo City.') }}</p>
        </div>

        <nav class="public-footer-links" aria-label="Footer navigation">
            <h2>Explore</h2>
            <a href="{{ $onHomePage ? '#fleet' : route('home').'#fleet' }}">Our Fleet</a>
            <a href="{{ $onHomePage ? '#how-it-works' : route('home').'#how-it-works' }}">How It Works</a>
            <a href="{{ $onHomePage ? '#about' : route('home').'#about' }}">About</a>
            <a href="{{ $onHomePage ? '#faq' : route('home').'#faq' }}">FAQ</a>
            <a href="{{ $onHomePage ? '#contact' : route('home').'#contact' }}">Contact</a>
        </nav>

        <div class="public-footer-account">
            <h2>Account</h2>
            @auth
                @if (auth()->user()->isCustomer())
                    <a href="{{ route('account') }}">My account</a>
                    <a href="{{ route('account.profile') }}">Profile</a>
                    @if (auth()->user()->hasVerifiedEmail())
                        <a href="{{ route('account.bookings') }}">My Bookings</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                @elseif (auth()->user()->isAdmin() || auth()->user()->isManager())
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}">Sign in</a>
                <a href="{{ route('register') }}">Create account</a>
            @endauth
        </div>

        <div class="public-footer-contact">
            <h2>Get in touch</h2>
            @include('partials.public-contact-details', ['socialVariant' => 'footer'])
        </div>
    </div>

    <div class="public-footer-bottom">
        <span>&copy; {{ now()->year }} {{ config('fleetguard.name', 'FleetGuard') }}. All rights reserved.</span>
        <a href="{{ route('home') }}">Back to top <i class="bi bi-arrow-up" aria-hidden="true"></i></a>
    </div>
</footer>
