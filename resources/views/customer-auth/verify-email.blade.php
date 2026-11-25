@extends('layouts.app')

@section('title', 'Verify Your Email | FleetGuard')
@section('meta-description', 'Verify your email address to activate customer account features on FleetGuard.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card verification-card" aria-labelledby="customer-auth-title">
            <aside class="verification-story" aria-label="FleetGuard account security">
                <span class="verification-icon" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
                <p class="verification-story-kicker">FleetGuard <span>·</span> Customer account</p>
                <h2>Every journey<br><span>begins with trust.</span></h2>
                <p class="verification-story-copy">A quick email confirmation keeps your account and booking details protected.</p>

                <div class="verification-progress">
                    <span class="verification-progress-number">01</span>
                    <span class="verification-progress-copy">
                        <strong>Account verification</strong>
                        <small>Your final setup step</small>
                    </span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </div>

                <p class="verification-assurance"><i class="bi bi-shield-check" aria-hidden="true"></i> Your details stay private and secure.</p>
            </aside>

            <div class="verification-panel">
                <div class="verification-panel-top">
                    <span class="verification-secure-label"><i class="bi bi-lock-fill" aria-hidden="true"></i> Secure verification</span>
                    <span class="verification-step-count">01 <span>/</span> 01</span>
                </div>

                <p class="customer-eyebrow"><span aria-hidden="true"></span> Account verification</p>
                <h1 id="customer-auth-title">Verify your email.</h1>
                <p class="customer-card-intro">Enter the six-digit code sent to your inbox to activate your account.</p>

                <div class="verification-address">
                    <i class="bi bi-envelope-check" aria-hidden="true"></i>
                    <span>
                        <small>Code sent to</small>
                        <strong>{{ auth()->user()->email }}</strong>
                    </span>
                </div>

                @if (session('status'))
                    <p class="customer-status" role="status" aria-live="polite" aria-atomic="true">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <div class="customer-errors" role="alert">
                        <p>{{ $errors->first() }}</p>
                    </div>
                @endif

                <form class="customer-form verification-form" method="POST" action="{{ route('verification.verify') }}">
                    @csrf
                    <div class="customer-field">
                        <label for="code">6-digit verification code</label>
                        <div class="customer-input-wrap verification-code-wrap">
                            <input
                                id="code"
                                name="code"
                                class="verification-code-input"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                aria-describedby="code-hint"
                                placeholder="000000"
                                required
                            >
                        </div>
                        <small id="code-hint"><i class="bi bi-clock" aria-hidden="true"></i> The code expires in 5 minutes.</small>
                    </div>
                    <button class="customer-submit verification-submit" type="submit">Verify and continue <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </form>

                <div class="verification-resend">
                    <span>Didn't receive the email?</span>
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit">Resend code</button>
                    </form>
                </div>

                <div class="verification-panel-footer">
                    <a class="verification-continue" href="{{ route('account') }}">Return to account <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    <form class="verification-signout" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out</button>
                    </form>
                </div>
            </div>
        </section>
    </main>
@endsection
