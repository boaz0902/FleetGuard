@extends('layouts.app')

@section('title', 'FleetGuard | Your Journey. Your Freedom.')

@section('content')
    @include('partials.public-navigation')

    <main id="main-content">
        <section class="hero" aria-labelledby="hero-title" data-hero>
            <div class="hero-image" aria-hidden="true">
                <img
                    src="{{ asset('images/hero/fleetguard-hero.jpg') }}"
                    srcset="{{ asset('images/hero/fleetguard-hero-800.jpg') }} 800w, {{ asset('images/hero/fleetguard-hero-1600.jpg') }} 1600w, {{ asset('images/hero/fleetguard-hero.jpg') }} 2400w"
                    sizes="100vw"
                    alt=""
                    width="2400"
                    height="1600"
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
                        <i class="bi bi-arrow-right button-arrow" aria-hidden="true"></i>
                    </a>
                    <a class="button button-quiet" href="#experience">
                        <i class="bi bi-arrow-down-short play-icon" aria-hidden="true"></i>
                        <span>Discover more</span>
                    </a>
                </div>
            </div>

            <aside class="hero-rail" aria-label="Panabo City, section 1 of 4" data-hero-rail>
                <span class="hero-rail-index">01 / 04</span>
                <span class="hero-rail-rule" aria-hidden="true"></span>
                <span class="hero-rail-location"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Panabo City</span>
            </aside>

            <a class="scroll-cue" href="#booking" aria-label="Scroll to vehicle search">
                <span class="scroll-cue-label">Scroll</span>
                <span class="scroll-cue-track" aria-hidden="true"><span></span></span>
            </a>

            <section class="booking-panel" id="booking" aria-labelledby="booking-title" data-booking-panel>
                <div class="booking-heading">
                    <p class="booking-kicker">A better way to go</p>
                    <h2 id="booking-title">Find your drive.</h2>
                </div>

                <form class="booking-form" data-booking-form novalidate>
                    <div class="field-group">
                        <label for="pickup-location">Pickup location</label>
                        <div class="field-control">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            <input id="pickup-location" name="pickup_location" type="text" placeholder="Enter a city or location" autocomplete="address-level2" aria-describedby="pickup-location-error" data-booking-field required>
                        </div>
                        <p class="booking-field-error" id="pickup-location-error" data-field-error aria-live="polite"></p>
                    </div>

                    <div class="field-group">
                        <label for="pickup-date">Pickup date</label>
                        <div class="field-control">
                            <i class="bi bi-calendar-event" aria-hidden="true"></i>
                            <input id="pickup-date" name="pickup_date" type="date" aria-describedby="pickup-date-error" data-booking-field required data-pickup-date>
                        </div>
                        <p class="booking-field-error" id="pickup-date-error" data-field-error aria-live="polite"></p>
                    </div>

                    <div class="field-group">
                        <label for="return-date">Return date</label>
                        <div class="field-control">
                            <i class="bi bi-calendar-check" aria-hidden="true"></i>
                            <input id="return-date" name="return_date" type="date" aria-describedby="return-date-error" data-booking-field required data-return-date>
                        </div>
                        <p class="booking-field-error" id="return-date-error" data-field-error aria-live="polite"></p>
                    </div>

                    <div class="field-group">
                        <label for="vehicle-category">Vehicle category</label>
                        <div class="field-control select-control">
                            <i class="bi bi-grid" aria-hidden="true"></i>
                            <select id="vehicle-category" name="vehicle_category" aria-describedby="vehicle-category-error" data-booking-field required>
                                <option value="" selected disabled>Select a category</option>
                                <option value="any">Any category</option>
                                <option value="suv">SUV</option>
                                <option value="sedan">Sedan</option>
                                <option value="mpv">MPV</option>
                            </select>
                            <i class="bi bi-chevron-down select-chevron" aria-hidden="true"></i>
                        </div>
                        <p class="booking-field-error" id="vehicle-category-error" data-field-error aria-live="polite"></p>
                    </div>

                    <button class="search-button" type="submit" data-search-button>
                        <span data-search-label>Search available cars</span>
                        <i class="bi bi-search search-icon" aria-hidden="true"></i>
                    </button>
                </form>

                <p class="booking-message" role="status" aria-live="polite" data-booking-message></p>
            </section>
        </section>

        <section class="trust-strip" aria-label="Placeholder service highlights">
            <ul>
                <li><i class="bi bi-patch-check" aria-hidden="true"></i><span>Insured &amp; maintained <small>(placeholder)</small></span></li>
                <li><i class="bi bi-clock-history" aria-hidden="true"></i><span>Flexible pickup <small>(placeholder)</small></span></li>
                <li><i class="bi bi-receipt" aria-hidden="true"></i><span>Transparent rates <small>(placeholder)</small></span></li>
                <li><i class="bi bi-headset" aria-hidden="true"></i><span>Local support <small>(placeholder)</small></span></li>
            </ul>
        </section>

        <section class="experience-transition" id="experience" aria-label="The experience">
            <span class="transition-rule" aria-hidden="true" data-transition-rule></span>
            <p><span>01</span> / The experience <i class="bi bi-arrow-down-short" aria-hidden="true"></i></p>
            <span class="transition-rule" aria-hidden="true" data-transition-rule></span>
        </section>

        @include('sections.fleet-showcase', ['vehicles' => config('sample_fleet')])

        @include('sections.booking-experience', ['vehicles' => []])
    </main>
@endsection
