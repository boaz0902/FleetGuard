@extends('layouts.app')

@section('title', 'My Account | FleetGuard')
@section('meta-description', 'Review your FleetGuard customer profile and account activity.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-dashboard-page" id="main-content">
        <header class="customer-bookings-hero">
            <div class="customer-bookings-hero-inner">
                <div>
                    <p class="customer-hero-eyebrow">Customer account</p>
                    <h1 id="customer-account-title">{{ $user->name }}</h1>
                    <p class="customer-hero-email">{{ $user->email }}</p>
                </div>
                <div class="customer-hero-profile">
                    <span class="customer-avatar" aria-hidden="true">{{ $initials }}</span>
                    @if ($user->hasVerifiedEmail())
                        <span class="customer-hero-verified"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Verified</span>
                    @else
                        <span class="customer-hero-unverified"><i class="bi bi-envelope-exclamation" aria-hidden="true"></i> Verify email</span>
                    @endif
                </div>
            </div>
        </header>

        <div class="customer-dashboard-content">
            @if (session('status'))
                <p class="customer-status" role="status">{{ session('status') }}</p>
            @endif

            @if (! $user->hasVerifiedEmail())
                <section class="customer-card customer-verification-callout">
                    <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Verify your email address</strong>
                        <p>Verification protects your account and lets FleetGuard safely connect an existing guest customer record.</p>
                    </div>
                    <a href="{{ route('verification.notice') }}">Verify email</a>
                </section>
            @else
                <section class="customer-account-summary" aria-label="Booking summary">
                    @foreach ([
                        ['label' => 'Upcoming', 'count' => $bookingSummary['upcoming'], 'icon' => 'calendar-event'],
                        ['label' => 'Active', 'count' => $bookingSummary['active'], 'icon' => 'key'],
                        ['label' => 'Completed', 'count' => $bookingSummary['completed'], 'icon' => 'check2-circle'],
                    ] as $metric)
                        <article class="customer-summary-card">
                            <span class="customer-summary-icon"><i class="bi bi-{{ $metric['icon'] }}" aria-hidden="true"></i></span>
                            <span><strong>{{ number_format($metric['count']) }}</strong><small>{{ $metric['label'] }}</small></span>
                        </article>
                    @endforeach
                </section>

                @if ($bookingSummary['next_booking'])
                    <section class="customer-card customer-next-booking">
                        <div>
                            <p class="customer-eyebrow"><span aria-hidden="true"></span> Next upcoming booking</p>
                            <h2>{{ $bookingSummary['next_booking']->vehicle->name }}</h2>
                            <p>{{ $bookingSummary['next_booking']->pickup_datetime->format('M j, Y · g:i A') }} · {{ $bookingSummary['next_booking']->pickup_location }}</p>
                        </div>
                        <a class="customer-action-link" href="{{ route('bookings.show', $bookingSummary['next_booking']) }}">View details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </section>
                @else
                    <section class="customer-card customer-next-booking">
                        <div>
                            <p class="customer-eyebrow"><span aria-hidden="true"></span> Your FleetGuard account</p>
                            <h2>Ready for your next journey?</h2>
                            <p>Your upcoming and past reservations will appear here.</p>
                        </div>
                        <a class="customer-action-link" href="{{ route('home') }}#fleet">Browse the fleet <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </section>
                @endif
            @endif

            <div class="customer-account-actions">
                @if ($user->hasVerifiedEmail())
                    <a class="customer-submit" href="{{ route('account.bookings') }}">View My Bookings <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                @endif
                <a class="customer-action-link" href="{{ route('home') }}#booking">Book a vehicle <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </main>
@endsection
