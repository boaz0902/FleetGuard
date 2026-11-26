@extends('layouts.app')

@section('title', 'Browse the Fleet | FleetGuard')
@section('meta-description', 'Compare vehicle categories, passenger capacity, transmission and fuel type, then view details and check availability.')

@section('content')
    @include('partials.public-navigation')
    <main id="main-content" class="showroom-page">
        <header class="showroom-header">
            <div class="showroom-header-copy">
                <nav class="breadcrumbs" aria-label="Breadcrumb">
                    <a href="{{ route('home') }}">Home</a><i class="bi bi-chevron-right" aria-hidden="true"></i><span aria-current="page">Fleet</span>
                </nav>
                <p class="eyebrow">The FleetGuard Collection</p>
                <h1>Browse our car rental fleet.</h1>
                <p>Review vehicle specifications, displayed rates and available dates before sending a request.</p>
            </div>
        </header>

        <section class="availability-bar" aria-label="Check vehicle availability">
            <form method="GET" action="{{ route('fleet') }}" class="availability-form">
                <label>Pickup date<input type="date" name="pickup_date" value="{{ request('pickup_date') }}" data-fleet-pickup></label>
                <label>Return date<input type="date" name="return_date" value="{{ request('return_date') }}" data-fleet-return></label>
                <label>Pickup location<input type="text" name="pickup_location" value="{{ request('pickup_location') }}" placeholder="Panabo City"></label>
                <button class="button button-primary" type="submit">Check Availability</button>
            </form>
        </section>

        <section class="showroom-shell" data-fleet data-fleet-query="{{ request()->getQueryString() }}">
            <div class="showroom-toolbar">
                <div class="category-chips" role="group" aria-label="Filter vehicles by category" data-fleet-filters>
                    <button type="button" class="is-active" data-filter="all" aria-pressed="true">All</button>
                    @foreach ($categories as $category)
                        <button type="button" data-filter="{{ strtolower($category) }}" aria-pressed="false">{{ ucfirst($category) }}</button>
                    @endforeach
                </div>
                <p aria-live="polite" data-fleet-count>Showing {{ $vehicles->count() }} vehicles</p>
            </div>

            @if ($vehicles->isEmpty())
                <div class="showroom-empty">
                    <i class="bi bi-car-front" aria-hidden="true"></i>
                    <h2>No vehicles match these filters</h2>
                    <p>Try another category or clear your filters.</p>
                    <a class="button button-primary" href="{{ route('fleet') }}">Clear filters</a>
                </div>
            @else
                <div class="showroom-grid">
                    @foreach ($vehicles as $index => $vehicle)
                        <article class="showroom-card" data-fleet-card data-category="{{ strtolower($vehicle->category) }}">
                            <a class="showroom-card-image" href="{{ route('fleet.show', $vehicle) }}">
                                @if ($vehicle->image_path)
                                    <img src="{{ asset($vehicle->image_path) }}" alt="{{ $vehicle->name }}" width="1000" height="650" loading="lazy">
                                @else
                                    <span aria-hidden="true"><i class="bi bi-car-front"></i></span>
                                @endif
                            </a>
                            <div class="showroom-card-body">
                                <p class="showroom-category">{{ strtoupper($vehicle->category) }}</p>
                                <h2><a href="{{ route('fleet.show', $vehicle) }}">{{ $vehicle->name }}</a></h2>
                                <ul aria-label="Vehicle specifications">
                                    <li><i class="bi bi-people" aria-hidden="true"></i>{{ $vehicle->passenger_capacity }} passengers</li>
                                    <li><i class="bi bi-gear-wide-connected" aria-hidden="true"></i>{{ $vehicle->transmission }}</li>
                                    <li><i class="bi bi-fuel-pump" aria-hidden="true"></i>{{ $vehicle->fuel_type }}</li>
                                </ul>
                                <p class="showroom-price"><strong>₱{{ number_format((float) $vehicle->daily_rate, 2) }}</strong><span>/ day</span></p>
                                <div class="showroom-card-actions">
                                    <a class="button button-secondary" href="{{ route('fleet.show', $vehicle) }}">View Details</a>
                                    <a class="button button-primary" href="{{ route('booking') }}?vehicle={{ $vehicle->id }}&pickup_date={{ request('pickup_date') }}&return_date={{ request('return_date') }}&pickup_location={{ urlencode(request('pickup_location', '')) }}">Book This Car</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="showroom-reassurance" aria-label="Rental reassurance">
            <div><i class="bi bi-car-front" aria-hidden="true"></i><span><strong>Vehicle details</strong><small>Specifications and daily rates shown</small></span></div>
            <div><i class="bi bi-calendar-check" aria-hidden="true"></i><span><strong>Availability requests</strong><small>Choose dates to ask about a vehicle</small></span></div>
            <div><i class="bi bi-clipboard-check" aria-hidden="true"></i><span><strong>Request review</strong><small>FleetGuard reviews each request</small></span></div>
        </section>
    </main>
@endsection
