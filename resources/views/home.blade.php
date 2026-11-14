@extends('layouts.app')

@section('title', 'FleetGuard | Your Journey. Your Freedom.')

@section('content')
    <header class="site-header" data-site-header>
        <div class="nav-shell">
            <a class="brand" href="{{ url('/') }}" aria-label="FleetGuard home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none">
                        <path d="M20 3.5 34 8.7v10.8c0 8.4-5.7 14.2-14 17-8.3-2.8-14-8.6-14-17V8.7L20 3.5Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="m11.5 22.5 1.7-5.2c.3-.9 1.1-1.5 2-1.5h9.6c.9 0 1.7.6 2 1.5l1.7 5.2v4h-2v-2H13.5v2h-2v-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                        <path d="M13.8 21h12.4M15 24h.1m9.8 0h.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="brand-wordmark">FLEET<span>GUARD</span></span>
            </a>

            <button class="menu-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="primary-navigation" data-menu-toggle>
                <i class="bi bi-list" aria-hidden="true"></i>
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>

            <nav class="primary-navigation" id="primary-navigation" aria-label="Main navigation" data-navigation>
                <a href="#fleet" data-public-nav-link>Our Fleet</a>
                <a href="#experience" data-public-nav-link>How It Works</a>
                <a href="#experience" data-public-nav-link>About</a>
                <a href="#experience" data-public-nav-link>Contact</a>
                <a class="nav-cta" href="#booking" data-public-nav-link>Book a Vehicle <i class="bi bi-arrow-up-right nav-arrow" aria-hidden="true"></i></a>
            </nav>
        </div>
    </header>

    <main id="main-content">
        <section class="hero" aria-labelledby="hero-title" data-hero>
            <div class="hero-image" aria-hidden="true">
                <img
                    src="{{ asset('images/hero/fleetguard-hero.jpg') }}"
                    alt=""
                    fetchpriority="high"
                    decoding="async"
                    data-hero-image
                >
            </div>
            <div class="hero-shade" aria-hidden="true"></div>
            <div class="hero-grain" aria-hidden="true"></div>

            <div class="hero-content">
                <p class="eyebrow hero-eyebrow" data-reveal>
                    <span class="eyebrow-line" aria-hidden="true"></span>
                    Premium car rental experience
                </p>

                <h1 id="hero-title" class="hero-title">
                    <span class="headline-line"><span data-headline-line>Your journey.</span></span>
                    <span class="headline-line headline-line-accent"><span data-headline-line>Your freedom.</span></span>
                </h1>

                <p class="hero-description" data-reveal>
                    Discover a better way to move. Explore our fleet and experience every journey with confidence.
                </p>

                <div class="hero-actions" data-reveal>
                    <a class="button button-primary" href="#fleet">
                        Explore our fleet
                        <i class="bi bi-arrow-up-right button-arrow" aria-hidden="true"></i>
                    </a>
                    <a class="button button-quiet" href="#experience">
                        <i class="bi bi-arrow-down play-icon" aria-hidden="true"></i>
                        Discover more
                    </a>
                </div>
            </div>

            <div class="hero-location" aria-label="Location: Panabo City">
                <span class="location-dot" aria-hidden="true"></span>
                Panabo City
            </div>

            <a class="scroll-cue" href="#booking" aria-label="Scroll to vehicle search">
                <span class="scroll-cue-label">Scroll to explore</span>
                <span class="scroll-cue-track" aria-hidden="true"><span></span></span>
            </a>

            <section class="booking-panel" id="booking" aria-labelledby="booking-title" data-booking-panel>
                <div class="booking-heading">
                    <p class="booking-kicker">A better way to go</p>
                    <h2 id="booking-title">Find your drive.</h2>
                </div>

                <form class="booking-form" data-booking-form>
                    <div class="field-group">
                        <label for="pickup-location">Pickup location</label>
                        <div class="field-control">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            <input id="pickup-location" name="pickup_location" type="text" placeholder="Enter a city or location" autocomplete="address-level2" required>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="pickup-date">Pickup date</label>
                        <div class="field-control">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <input id="pickup-date" name="pickup_date" type="date" aria-label="Pickup date" required data-pickup-date>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="return-date">Return date</label>
                        <div class="field-control">
                            <i class="bi bi-calendar3" aria-hidden="true"></i>
                            <input id="return-date" name="return_date" type="date" aria-label="Return date" required data-return-date>
                        </div>
                    </div>

                    <div class="field-group">
                        <label for="vehicle-category">Vehicle category</label>
                        <div class="field-control select-control">
                            <i class="bi bi-car-front" aria-hidden="true"></i>
                            <select id="vehicle-category" name="vehicle_category" required>
                                <option value="" selected disabled>Select a category</option>
                                <option value="any">Any category</option>
                                <option value="suv">SUV</option>
                                <option value="sedan">Sedan</option>
                                <option value="mpv">MPV</option>
                            </select>
                            <i class="bi bi-chevron-down select-chevron" aria-hidden="true"></i>
                        </div>
                    </div>

                    <button class="search-button" type="submit">
                        Search available cars
                        <i class="bi bi-arrow-up-right search-icon" aria-hidden="true"></i>
                    </button>
                </form>

                <p class="booking-message" role="status" aria-live="polite" data-booking-message></p>
            </section>
        </section>

        <section class="experience-transition" id="experience" aria-label="The experience">
            <span class="transition-rule" aria-hidden="true"></span>
            <p><span>01</span> / The experience</p>
            <span class="transition-rule" aria-hidden="true"></span>
        </section>

        @include('sections.fleet-showcase', ['vehicles' => config('sample_fleet')])

        @include('sections.booking-experience', ['vehicles' => []])
    </main>
@endsection
