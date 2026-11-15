@extends('layouts.app')

@section('title', 'My Account | FleetGuard')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page customer-page-account" id="main-content">
        <section class="customer-card customer-account-card" aria-labelledby="customer-account-title">
            <p class="customer-eyebrow"><span aria-hidden="true"></span> Customer account</p>
            <div class="customer-account-heading">
                <span class="customer-avatar" aria-hidden="true">{{ $initials }}</span>
                <div>
                    <h1 id="customer-account-title">{{ $user->name }}</h1>
                    <p>{{ $user->email }}</p>
                </div>
            </div>

            @if (session('status'))
                <p class="customer-status" role="status">{{ session('status') }}</p>
            @endif

            @if (! $user->hasVerifiedEmail())
                <div class="customer-verification-callout">
                    <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Verify your email address</strong>
                        <p>Verification protects your account and lets FleetGuard safely connect an existing guest customer record.</p>
                    </div>
                    <a href="{{ route('verification.notice') }}">Verify email</a>
                </div>
            @else
                <p class="customer-verified"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Email verified</p>
            @endif

            <div class="customer-account-actions">
                <a class="customer-submit" href="{{ route('home') }}#booking">Book a vehicle <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                <p>Your account is ready for customer booking. Booking history is not part of this release.</p>
            </div>
        </section>
    </main>
@endsection
