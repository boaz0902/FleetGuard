@extends('layouts.app')

@section('title', 'Car Rental in Panabo City | FleetGuard')
@section('meta-description', 'Looking for car rental in Panabo City? Compare listed vehicles and displayed daily rates, check availability for your dates, and send a request for FleetGuard review.')

@section('content')
    @include('partials.public-navigation')

    <main id="main-content">
        <section class="hero" id="top" aria-labelledby="hero-title" data-hero>
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
                    Explore listed vehicles
                </p>

                <h1 id="hero-title" class="hero-title">
                    <span class="headline-line"><span data-headline-line>Car Rental in</span></span>
                    <span class="headline-line headline-line-accent"><span data-headline-line>Panabo City.</span></span>
                </h1>

                <p class="hero-description" data-reveal>
                    <strong>Drive Your Next Journey.</strong> Experience convenient and reliable car rental services in Panabo City. Explore our vehicles and find the right ride for your next journey.
                </p>

                <div class="hero-actions" data-reveal>
                    <a class="button button-primary" href="{{ route('fleet') }}">
                        Explore our fleet
                        <i class="bi bi-arrow-right button-arrow" aria-hidden="true"></i>
                    </a>
                    <a class="button button-quiet" href="#how-it-works">
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

            <a class="scroll-cue" href="#vehicle-search" aria-label="Scroll to vehicle search">
                <span class="scroll-cue-label">Scroll</span>
                <span class="scroll-cue-track" aria-hidden="true"><span></span></span>
            </a>

            <section class="booking-panel" id="vehicle-search" aria-labelledby="booking-title" data-booking-panel>
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

        <section class="trust-strip" aria-label="Service highlights">
            <ul>
                <li><i class="bi bi-car-front" aria-hidden="true"></i><span>Listed vehicles</span></li>
                <li><i class="bi bi-calendar-check" aria-hidden="true"></i><span>Check your dates</span></li>
                <li><i class="bi bi-receipt" aria-hidden="true"></i><span>Displayed daily rates</span></li>
                <li><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Requests reviewed</span></li>
            </ul>
        </section>

        <section class="featured-fleet" id="fleet" aria-labelledby="featured-fleet-title">
            <div class="featured-fleet-heading">
                <div>
                    <p class="fleet-eyebrow homepage-section-label"><span><strong>01</strong> / Featured vehicles</span></p>
                    <h2 id="featured-fleet-title">Explore Our Car Rental Fleet</h2>
                </div>
                <a class="button button-secondary" href="{{ route('fleet') }}">Browse the full fleet <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="featured-fleet-grid">
                @foreach ($vehicles->take(4) as $vehicle)
                    <article class="featured-fleet-card">
                        <a class="featured-fleet-image" href="{{ route('fleet.show', $vehicle['id']) }}" aria-label="View {{ $vehicle['name'] }} details">
                            @if ($vehicle['image'])
                                <img src="{{ asset($vehicle['image']) }}" alt="{{ $vehicle['name'] }}" width="1000" height="650" loading="lazy">
                            @else
                                <span class="featured-fleet-fallback" aria-hidden="true"><i class="bi bi-car-front"></i></span>
                            @endif
                            <span class="featured-fleet-image-shade" aria-hidden="true"></span>
                            <span class="featured-fleet-index" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }} <i></i> FLEETGUARD COLLECTION</span>
                        </a>
                        <div class="featured-fleet-card-body">
                            <div class="featured-fleet-card-meta">
                                <p>{{ strtoupper($vehicle['category_label']) }}</p>
                                <span>Displayed daily rate</span>
                            </div>
                            <h3><a href="{{ route('fleet.show', $vehicle['id']) }}">{{ $vehicle['name'] }}</a></h3>
                            <ul class="featured-fleet-specs" aria-label="Vehicle specifications">
                                <li><i class="bi bi-people" aria-hidden="true"></i>{{ $vehicle['passengers'] }}</li>
                                <li><i class="bi bi-gear-wide-connected" aria-hidden="true"></i>{{ $vehicle['transmission'] }}</li>
                            </ul>
                            <div class="featured-fleet-card-footer">
                                <div class="featured-fleet-rate"><span>Daily rate</span><strong>{{ $vehicle['price'] }} <small>/ day</small></strong></div>
                                <a class="featured-fleet-details" href="{{ route('fleet.show', $vehicle['id']) }}">View details <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @include('sections.how-it-works')

        @include('sections.about')

        @include('sections.testimonials', ['testimonials' => $testimonials])

        @include('sections.faq')

        @include('sections.contact')

        <section class="home-closing-cta" aria-labelledby="closing-cta-title">
            <p class="fleet-eyebrow homepage-section-label"><span>Make your next trip yours</span></p>
            <h2 id="closing-cta-title">Your next drive starts with a request.</h2>
            <p>Choose your dates and a listed vehicle. FleetGuard will review your request and confirm availability and final rates.</p>
            <a class="button button-primary" href="{{ route('booking') }}">Start a booking request <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        </section>
    </main>
@endsection
