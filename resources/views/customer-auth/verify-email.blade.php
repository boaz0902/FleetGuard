@extends('layouts.app')

@section('title', 'Verify Your Email | FleetGuard')
@section('meta-description', 'Verify your email address to activate customer account features on FleetGuard.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card verification-card" aria-labelledby="customer-auth-title">
            <span class="verification-icon" aria-hidden="true"><i class="bi bi-envelope-check-fill"></i></span>
            <p class="customer-eyebrow"><span aria-hidden="true"></span> Account verification</p>
            <h1 id="customer-auth-title">Verify your email address.</h1>
            <p class="customer-card-intro">Please verify your email to continue. Confirming your address unlocks your booking history and helps connect any matching guest customer record.</p>

            <div class="verification-address">
                <span>Account email</span>
                <strong>{{ auth()->user()->email }}</strong>
            </div>

            <ol class="verification-steps">
                <li><span>01</span><p>Check your inbox, including your spam folder.</p></li>
                <li><span>02</span><p>Open the secure verification link in the email.</p></li>
                <li><span>03</span><p>Return to your account to continue.</p></li>
            </ol>

            @if (session('status'))
                <p class="customer-status" role="status" aria-live="polite" aria-atomic="true">{{ session('status') }}</p>
            @endif

            <form class="customer-form" method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="customer-submit" type="submit">Send a new verification link <i class="bi bi-arrow-repeat" aria-hidden="true"></i></button>
            </form>

            <a class="verification-continue" href="{{ route('account') }}">Continue to your account <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>

            <form class="verification-signout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out</button>
            </form>
        </section>
    </main>
@endsection
