@extends('layouts.app')

@section('title', 'Profile & Security | FleetGuard')
@section('meta-description', 'Update your FleetGuard customer profile and password.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-dashboard-page customer-profile-page" id="main-content">
        @include('partials.customer-account-hero', [
            'heroTitle' => 'Profile & Security',
            'heroDescription' => $user->name.' · '.$user->email,
        ])

        <div class="customer-dashboard-content">
            @if (session('status'))
                <p class="customer-status" role="status" aria-live="polite">{{ session('status') }}</p>
            @endif

            @if (! $user->hasVerifiedEmail())
                <section class="customer-card customer-verification-callout">
                    <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>Verify your email address</strong>
                        <p>Your profile is available now. Verification protects your account and lets FleetGuard safely connect an existing guest customer record.</p>
                    </div>
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit">Resend verification code</button>
                    </form>
                </section>
            @endif

            <nav class="customer-quick-links" aria-label="Account shortcuts">
                <a class="customer-quick-link" href="{{ route('account') }}">
                    <span class="customer-quick-link-icon"><i class="bi bi-person-circle" aria-hidden="true"></i></span>
                    <span><strong>My account</strong><small>Return to your account overview.</small></span>
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
                @if ($user->hasVerifiedEmail())
                    <a class="customer-quick-link" href="{{ route('account.bookings') }}">
                        <span class="customer-quick-link-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                        <span><strong>My Bookings</strong><small>Review your reservations and their status.</small></span>
                        <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                @endif
            </nav>

            <div class="customer-profile-grid">
                <section class="customer-profile-card" aria-labelledby="profile-details-title">
                    <div class="customer-profile-card-heading">
                        <span class="customer-profile-card-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                        <div>
                            <h2 id="profile-details-title">Profile details</h2>
                            <p>Keep your contact details current.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('account.profile.update') }}" data-profile-form>
                        @csrf
                        @method('PUT')

                        <label class="customer-profile-field" for="profile-name">
                            <span>Name</span>
                            <span class="customer-profile-input-wrap">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <input id="profile-name" type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="profile-name-help{{ $errors->has('name') ? ' profile-name-error' : '' }}">
                            </span>
                            <small id="profile-name-help">Your name is shown in your FleetGuard account.</small>
                            @error('name')<small class="customer-profile-error" id="profile-name-error">{{ $message }}</small>@enderror
                        </label>

                        <label class="customer-profile-field" for="profile-phone">
                            <span>Phone</span>
                            <span class="customer-profile-input-wrap">
                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                <input id="profile-phone" type="tel" name="phone" value="{{ old('phone', $profilePhone) }}" maxlength="13" autocomplete="tel" placeholder="09XXXXXXXXX" required pattern="(?:\+63|0)9[0-9]{9}" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="profile-phone-help{{ $errors->has('phone') ? ' profile-phone-error' : '' }}">
                            </span>
                            <small id="profile-phone-help">Use a Philippine mobile number, such as 09XXXXXXXXX or +639XXXXXXXXX.</small>
                            @error('phone')<small class="customer-profile-error" id="profile-phone-error">{{ $message }}</small>@enderror
                        </label>

                        <label class="customer-profile-field" for="profile-email">
                            <span>Email</span>
                            <span class="customer-profile-input-wrap customer-profile-input-wrap--disabled">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                <input id="profile-email" type="email" value="{{ $user->email }}" disabled aria-describedby="profile-email-help">
                            </span>
                            <small id="profile-email-help">Your email is your sign-in and booking identity. Contact FleetGuard to change it.</small>
                        </label>

                        <button class="customer-profile-submit" type="submit" data-profile-submit>
                            <i class="bi bi-check2" aria-hidden="true"></i>
                            <span data-submit-label>Save profile</span>
                        </button>
                    </form>
                </section>

                <section class="customer-profile-card" aria-labelledby="password-security-title">
                    <div class="customer-profile-card-heading">
                        <span class="customer-profile-card-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                        <div>
                            <h2 id="password-security-title">Password and security</h2>
                            <p>Choose a strong password you do not use elsewhere.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('account.profile.password') }}" data-profile-form>
                        @csrf
                        @method('PUT')

                        @foreach ([
                            ['id' => 'current-password', 'name' => 'current_password', 'label' => 'Current password', 'autocomplete' => 'current-password'],
                            ['id' => 'new-password', 'name' => 'password', 'label' => 'New password', 'autocomplete' => 'new-password'],
                            ['id' => 'confirm-password', 'name' => 'password_confirmation', 'label' => 'Confirm new password', 'autocomplete' => 'new-password'],
                        ] as $passwordField)
                            <label class="customer-profile-field" for="{{ $passwordField['id'] }}">
                                <span>{{ $passwordField['label'] }}</span>
                                <span class="customer-profile-input-wrap customer-profile-input-wrap--password">
                                    <i class="bi bi-lock" aria-hidden="true"></i>
                                    <input id="{{ $passwordField['id'] }}" type="password" name="{{ $passwordField['name'] }}" autocomplete="{{ $passwordField['autocomplete'] }}" required aria-invalid="{{ $errors->has($passwordField['name']) ? 'true' : 'false' }}" aria-describedby="{{ $passwordField['id'] }}-help{{ $errors->has($passwordField['name']) ? ' '.$passwordField['id'].'-error' : '' }}" @if ($passwordField['name'] === 'password') data-password-strength-input @endif>
                                    <button class="customer-password-toggle" type="button" aria-label="Show {{ strtolower($passwordField['label']) }}" aria-controls="{{ $passwordField['id'] }}" aria-pressed="false" data-password-toggle data-password-toggle-label="{{ strtolower($passwordField['label']) }}">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </span>
                                @if ($passwordField['name'] === 'password')
                                    <small id="{{ $passwordField['id'] }}-help" data-password-strength>Use at least 8 characters. A longer, unique password is easier to keep secure.</small>
                                @elseif ($passwordField['name'] === 'current_password')
                                    <small id="{{ $passwordField['id'] }}-help">Enter your current password to confirm this change.</small>
                                @else
                                    <small id="{{ $passwordField['id'] }}-help">Re-enter your new password to confirm.</small>
                                @endif
                                @error($passwordField['name'])<small class="customer-profile-error" id="{{ $passwordField['id'] }}-error">{{ $message }}</small>@enderror
                            </label>
                        @endforeach

                        <button class="customer-profile-submit" type="submit" data-profile-submit>
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <span data-submit-label>Change password</span>
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </main>
@endsection
