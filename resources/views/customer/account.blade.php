@extends('layouts.app')

@section('title', 'My Account | FleetGuard')
@section('meta-description', 'Review your FleetGuard customer profile and account activity.')

@section('content')
    @include('partials.public-navigation')

    @php
        $firstName = collect(preg_split('/\s+/', trim($user->name)) ?: [])->first() ?: 'there';
    @endphp

    <main class="customer-dashboard-page" id="main-content">
        @include('partials.customer-account-hero', [
            'heroTitle' => 'Welcome back, '.$firstName,
            'heroDescription' => $user->name.' · '.$user->email,
        ])

        <div class="customer-dashboard-content">
            @if (session('status'))
                <p class="customer-status" role="status" aria-live="polite">{{ session('status') }}</p>
            @endif

            @if (! $user->hasVerifiedEmail())
                <section class="customer-card customer-verification-callout">
                    <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Verify your email address</strong>
                        <p>Verification protects your account and lets FleetGuard safely connect an existing guest customer record.</p>
                    </div>
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit">Resend verification code</button>
                    </form>
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

            <nav class="customer-quick-links" aria-label="Account shortcuts">
                <a class="customer-quick-link" href="{{ route('account.profile') }}">
                    <span class="customer-quick-link-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
                    <span><strong>Profile</strong><small>Update your details and password.</small></span>
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
                <a class="customer-quick-link" href="{{ route('account.legal-policies') }}">
                    <span class="customer-quick-link-icon"><i class="bi bi-file-earmark-check" aria-hidden="true"></i></span>
                    <span><strong>Legal &amp; Policies</strong><small>View current policies and your acceptance history.</small></span>
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
                @if ($user->hasVerifiedEmail())
                    <a class="customer-quick-link" href="{{ route('account.bookings') }}">
                        <span class="customer-quick-link-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                        <span><strong>My Bookings</strong><small>Review your reservations and their status.</small></span>
                        <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                @endif
                <a class="customer-quick-link" href="{{ route('home') }}#fleet">
                    <span class="customer-quick-link-icon"><i class="bi bi-car-front" aria-hidden="true"></i></span>
                    <span><strong>Browse the fleet</strong><small>Find a vehicle for your next journey.</small></span>
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
            </nav>
        </div>
    </main>
@endsection
