@extends('layouts.app')

@section('title', 'Create Customer Account | FleetGuard')
@section('meta-description', 'Create a FleetGuard customer account to manage your details and follow verified booking requests.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card" aria-labelledby="customer-auth-title">
            <p class="customer-eyebrow"><span aria-hidden="true"></span> Start with FleetGuard</p>
            <h1 id="customer-auth-title">Create your account.</h1>
            <p class="customer-card-intro">Verify your email to safely connect any existing guest customer record.</p>
            @if ($prefillEmail)
                <p class="customer-card-intro">Your booking request will link to this account after you verify this email address.</p>
            @endif

            @if ($errors->any())
                <div class="customer-errors" role="alert">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="customer-form" method="POST" action="{{ route('register.submit') }}">
                @csrf
                <div class="customer-field">
                    <label for="name">Full name</label>
                    <div class="customer-input-wrap">
                        <i class="bi bi-person customer-input-icon" aria-hidden="true"></i>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" required autofocus>
                    </div>
                </div>
                <div class="customer-field">
                    <label for="email">Email address</label>
                    <div class="customer-input-wrap">
                        <i class="bi bi-envelope customer-input-icon" aria-hidden="true"></i>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $prefillEmail) }}"
                            autocomplete="email"
                            maxlength="255"
                            @readonly($prefillEmail !== null)
                            required
                        >
                    </div>
                </div>
                <div class="customer-field">
                    <label for="password">Password</label>
                    <div class="customer-input-wrap customer-input-wrap--password">
                        <i class="bi bi-lock customer-input-icon" aria-hidden="true"></i>
                        <input id="password" name="password" type="password" autocomplete="new-password" required>
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
                    <small>Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol.</small>
                </div>
                <div class="customer-field">
                    <label for="password_confirmation">Confirm password</label>
                    <div class="customer-input-wrap customer-input-wrap--password">
                        <i class="bi bi-lock customer-input-icon" aria-hidden="true"></i>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        <button
                            class="customer-password-toggle"
                            type="button"
                            aria-controls="password_confirmation"
                            aria-label="Show confirmation password"
                            aria-pressed="false"
                            data-password-toggle
                            data-password-toggle-label="confirmation password"
                        ><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <button class="customer-submit" type="submit">Create account <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </form>

            <div class="customer-card-links">
                <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
@endsection
