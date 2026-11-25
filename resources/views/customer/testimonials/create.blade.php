@extends('layouts.app')

@section('title', 'Share Your Experience | FleetGuard')
@section('meta-description', 'Share feedback about your completed FleetGuard booking.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-dashboard-page customer-testimonial-page" id="main-content">
        @include('partials.customer-account-hero', [
            'heroTitle' => 'Share your experience',
            'heroDescription' => 'Your feedback helps us make every journey better.',
            'heroBackUrl' => route('account.bookings'),
            'heroBackText' => 'Back to My Bookings',
        ])

        <div class="customer-dashboard-content">
            @if ($errors->any())
                <p class="customer-booking-alert" role="alert">{{ $errors->first() }}</p>
            @endif

            <section class="customer-testimonial-booking" aria-label="Completed booking">
                <span class="customer-testimonial-booking-icon"><i class="bi bi-car-front" aria-hidden="true"></i></span>
                <div>
                    <p class="customer-eyebrow">Completed booking</p>
                    <h2>{{ $booking->vehicle->name }}</h2>
                    <span>{{ $booking->booking_reference }}</span>
                </div>
                <span class="customer-testimonial-completed"><i class="bi bi-check-circle" aria-hidden="true"></i> Completed</span>
            </section>

            <section class="customer-testimonial-panel" aria-labelledby="testimonial-form-title">
                <div class="customer-testimonial-heading">
                    <p class="customer-eyebrow">Your review</p>
                    <h2 id="testimonial-form-title">How was your experience?</h2>
                    <p>Your review will be checked by our team before it can appear on the website.</p>
                </div>

                <form method="POST" action="{{ route('account.bookings.testimonials.store', $booking) }}" class="customer-testimonial-form">
                    @csrf

                    <fieldset class="customer-testimonial-rating">
                        <legend>Overall rating <span aria-hidden="true">*</span></legend>
                        <div class="customer-testimonial-stars">
                            @for ($rating = 1; $rating <= 5; $rating++)
                                <label class="customer-testimonial-star">
                                    <input
                                        type="radio"
                                        name="rating"
                                        value="{{ $rating }}"
                                        aria-label="{{ $rating }} out of 5 stars"
                                        required
                                        @checked((string) old('rating', '5') === (string) $rating)
                                    >
                                    <span aria-hidden="true"><i class="bi bi-star-fill"></i></span>
                                </label>
                            @endfor
                        </div>
                        @error('rating')<small class="customer-testimonial-field-error">{{ $message }}</small>@enderror
                    </fieldset>

                    <div class="customer-testimonial-field">
                        <label for="review-content">Your review</label>
                        <textarea
                            id="review-content"
                            name="review_content"
                            minlength="20"
                            maxlength="2000"
                            rows="6"
                            required
                            aria-describedby="review-content-help review-content-count"
                            data-testimonial-review-input
                        >{{ old('review_content') }}</textarea>
                        <div class="customer-testimonial-field-meta">
                            <small id="review-content-help">Share what went well or what we could improve.</small>
                            <small id="review-content-count" aria-live="polite"><span data-testimonial-review-count>{{ mb_strlen(old('review_content', '')) }}</span> / 2,000</small>
                        </div>
                        @error('review_content')<small class="customer-testimonial-field-error">{{ $message }}</small>@enderror
                    </div>

                    <fieldset class="customer-testimonial-consent">
                        <legend>Public display permission</legend>
                        <label>
                            <input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                            <span>I consent to public display of my review and the name <strong>{{ $publicDisplayName }}</strong>. I understand my review will be moderated before publication.</span>
                        </label>
                        @error('consent')<small class="customer-testimonial-field-error">{{ $message }}</small>@enderror
                    </fieldset>

                    <div class="customer-testimonial-honeypot" aria-hidden="true">
                        <label for="testimonial-website">Leave this field empty</label>
                        <input id="testimonial-website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="customer-testimonial-actions">
                        <a class="customer-testimonial-cancel" href="{{ route('account.bookings') }}">Cancel</a>
                        <button class="customer-submit" type="submit">Submit for review <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
                    </div>
                </form>
            </section>
        </div>
    </main>
@endsection
