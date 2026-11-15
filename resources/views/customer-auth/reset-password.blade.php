@extends('layouts.app')

@section('title', 'Choose a New Password | FleetGuard')
@section('meta-description', 'Choose a new password for your FleetGuard customer account.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card" aria-labelledby="customer-auth-title">
            <p class="customer-eyebrow"><span aria-hidden="true"></span> Account recovery</p>
            <h1 id="customer-auth-title">Choose a new password.</h1>
            <p class="customer-card-intro">Your new password must be at least 8 characters and include mixed case, a number, and a symbol.</p>

            @if ($errors->any())
                <div class="customer-errors" role="alert">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="customer-form" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="customer-field">
                    <label for="email">Email address</label>
                    <div class="customer-input-wrap">
                        <i class="bi bi-envelope customer-input-icon" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" maxlength="255" required>
                    </div>
                </div>
                <div class="customer-field">
                    <label for="password">New password</label>
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
                </div>
                <div class="customer-field">
                    <label for="password_confirmation">Confirm new password</label>
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
                <button class="customer-submit" type="submit">Save new password <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </form>
        </section>
    </main>
@endsection
