@extends('layouts.app')

@section('title', 'Reset Password | FleetGuard')
@section('meta-description', 'Request a secure password reset link for your FleetGuard customer account.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card" aria-labelledby="customer-auth-title">
            <p class="customer-eyebrow"><span aria-hidden="true"></span> Account recovery</p>
            <h1 id="customer-auth-title">Reset your password.</h1>
            <p class="customer-card-intro">Enter the email address for your customer account. If it exists, we’ll send a reset link.</p>

            @if (session('status'))
                <p class="customer-status" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="customer-errors" role="alert">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="customer-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="customer-field">
                    <label for="email">Email address</label>
                    <div class="customer-input-wrap">
                        <i class="bi bi-envelope customer-input-icon" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required autofocus>
                    </div>
                </div>
                <button class="customer-submit" type="submit">Send reset link <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </form>

            <div class="customer-card-links">
                <p>Remembered it? <a href="{{ route('login') }}">Back to sign in</a></p>
            </div>
        </section>
    </main>
@endsection
