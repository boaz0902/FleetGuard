@extends('layouts.app')

@section('title', 'Customer Sign In | FleetGuard')
@section('meta-description', 'Sign in to your FleetGuard customer account to review your account and booking requests.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card" aria-labelledby="customer-auth-title">
            <p class="customer-eyebrow"><span aria-hidden="true"></span> Customer account</p>
            <h1 id="customer-auth-title">Sign in to your account.</h1>
            <p class="customer-card-intro">Sign in to your FleetGuard customer account.</p>

            @if (session('status'))
                <p class="customer-status" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="customer-errors" role="alert">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="customer-form" method="POST" action="{{ route('login.submit') }}">
                @csrf
                <div class="customer-field">
                    <label for="email">Email address</label>
                    <div class="customer-input-wrap">
                        <i class="bi bi-envelope customer-input-icon" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required autofocus>
                    </div>
                </div>
                <div class="customer-field">
                    <label for="password">Password</label>
                    <div class="customer-input-wrap customer-input-wrap--password">
                        <i class="bi bi-lock customer-input-icon" aria-hidden="true"></i>
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <button
                            class="customer-password-toggle"
                            type="button"
                            aria-controls="password"
                            aria-label="Show password"
                            aria-pressed="false"
                            data-password-toggle
                            data-password-toggle-label="password"
                        ><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <button class="customer-submit" type="submit">Sign in <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </form>

            <div class="customer-card-links">
                <a href="{{ route('password.request') }}">Forgot your password?</a>
                <p>New to FleetGuard? <a href="{{ route('register') }}">Create an account</a></p>
            </div>
        </section>
    </main>
@endsection
