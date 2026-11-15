@extends('layouts.app')

@section('title', 'Choose a New Password | FleetGuard')

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
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" maxlength="255" required>
                </div>
                <div class="customer-field">
                    <label for="password">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                </div>
                <div class="customer-field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>
                <button class="customer-submit" type="submit">Save new password <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </form>
        </section>
    </main>
@endsection
