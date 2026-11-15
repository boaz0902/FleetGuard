@extends('layouts.app')

@section('title', 'Create Customer Account | FleetGuard')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card" aria-labelledby="customer-auth-title">
            <p class="customer-eyebrow"><span aria-hidden="true"></span> Start with FleetGuard</p>
            <h1 id="customer-auth-title">Create your account.</h1>
            <p class="customer-card-intro">Verify your email to safely connect any existing guest customer record.</p>

            @if ($errors->any())
                <div class="customer-errors" role="alert">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form class="customer-form" method="POST" action="{{ route('register.submit') }}">
                @csrf
                <div class="customer-field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" required autofocus>
                </div>
                <div class="customer-field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
                </div>
                <div class="customer-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    <small>Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol.</small>
                </div>
                <div class="customer-field">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>
                <button class="customer-submit" type="submit">Create account <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
            </form>

            <div class="customer-card-links">
                <p>Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
@endsection
