@extends('layouts.app')

@section('title', 'Verify Your Email | FleetGuard')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-page" id="main-content">
        <section class="customer-card" aria-labelledby="customer-auth-title">
            <p class="customer-eyebrow"><span aria-hidden="true"></span> One last step</p>
            <h1 id="customer-auth-title">Verify your email.</h1>
            <p class="customer-card-intro">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Verify it to connect any matching guest customer record to your account.</p>

            @if (session('status'))
                <p class="customer-status" role="status">{{ session('status') }}</p>
            @endif

            <form class="customer-form" method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="customer-submit" type="submit">Send a new verification link <i class="bi bi-arrow-repeat" aria-hidden="true"></i></button>
            </form>

            <div class="customer-card-links">
                <a href="{{ route('account') }}">Continue to your account</a>
            </div>
        </section>
    </main>
@endsection
