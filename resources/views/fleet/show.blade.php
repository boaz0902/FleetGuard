@extends('layouts.app')

@section('title', $vehicle->name.' | '.ucfirst($vehicle->category).' Vehicle | FleetGuard')
@section('meta-description', 'Explore '.$vehicle->name.', a '.strtolower($vehicle->category).' with '.$vehicle->passenger_capacity.' passenger capacity, '.$vehicle->transmission.' transmission and '.$vehicle->fuel_type.' fuel type. View details and send a booking request.')

@section('content')
    @include('partials.public-navigation')
    <main id="main-content" class="vehicle-details-page">
        <nav class="breadcrumbs vehicle-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><i class="bi bi-chevron-right" aria-hidden="true"></i><a href="{{ route('fleet') }}">Fleet</a><i class="bi bi-chevron-right" aria-hidden="true"></i><span aria-current="page">{{ $vehicle->name }}</span>
        </nav>
        <div class="vehicle-details-layout">
            <article class="vehicle-details-main">
                <section class="vehicle-gallery" aria-label="{{ $vehicle->name }} image">
                    @if ($vehicle->image_path)
                        <img src="{{ asset($vehicle->image_path) }}" alt="{{ $vehicle->name }}" width="1400" height="900">
                    @else
                        <span class="vehicle-gallery-fallback" aria-label="{{ $vehicle->name }} image unavailable"><i class="bi bi-car-front" aria-hidden="true"></i><small>FleetGuard vehicle</small></span>
                    @endif
                </section>
                <section class="vehicle-about">
                    <p class="eyebrow">{{ strtoupper($vehicle->category) }}</p>
                    <h1>{{ $vehicle->name }}</h1>
                    <p>{{ $vehicle->description ?: 'Review the vehicle specifications and rental details before sending a booking request.' }}</p>
                </section>
                <section class="vehicle-spec-grid" aria-label="Vehicle specifications">
                    <div><i class="bi bi-people" aria-hidden="true"></i><span>Passengers<strong>{{ $vehicle->passenger_capacity }}</strong></span></div>
                    <div><i class="bi bi-gear-wide-connected" aria-hidden="true"></i><span>Transmission<strong>{{ $vehicle->transmission }}</strong></span></div>
                    <div><i class="bi bi-fuel-pump" aria-hidden="true"></i><span>Fuel type<strong>{{ $vehicle->fuel_type }}</strong></span></div>
                    <div><i class="bi bi-grid" aria-hidden="true"></i><span>Category<strong>{{ ucfirst($vehicle->category) }}</strong></span></div>
                </section>
                <section class="rental-notes">
                    <h2>Rental notes</h2>
                    <ul>
                        <li><i class="bi bi-check2" aria-hidden="true"></i>Key vehicle specifications shown</li>
                        <li><i class="bi bi-check2" aria-hidden="true"></i>Displayed daily rate</li>
                        <li><i class="bi bi-info-circle" aria-hidden="true"></i>Final details are confirmed by FleetGuard</li>
                    </ul>
                </section>
            </article>

            <aside class="vehicle-booking-card" aria-label="Request this vehicle">
                <p class="eyebrow">{{ strtoupper($vehicle->category) }}</p>
                <h2>{{ $vehicle->name }}</h2>
                <p class="vehicle-details-rate"><strong>₱{{ number_format((float) $vehicle->daily_rate, 2) }}</strong><span>/ day</span></p>
                <form method="GET" action="{{ route('booking') }}" class="vehicle-date-form" data-vehicle-booking-form data-availability-url="{{ route('vehicles.availability') }}" data-vehicle-id="{{ $vehicle->id }}">
                    <input type="hidden" name="vehicle" value="{{ $vehicle->id }}">
                    <label for="vehicle-pickup-date">Pickup date<input id="vehicle-pickup-date" type="date" name="pickup_date" value="{{ request('pickup_date') }}" data-vehicle-pickup-date required></label>
                    <label for="vehicle-pickup-time">Pickup time
                        <select id="vehicle-pickup-time" name="pickup_time" data-vehicle-pickup-time required>
                            <option value="">Select time</option>
                            @foreach (['08:00' => '8:00 AM', '09:00' => '9:00 AM', '10:00' => '10:00 AM', '11:00' => '11:00 AM', '12:00' => '12:00 PM', '13:00' => '1:00 PM', '14:00' => '2:00 PM', '15:00' => '3:00 PM', '16:00' => '4:00 PM', '17:00' => '5:00 PM', '18:00' => '6:00 PM'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('pickup_time') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label for="vehicle-return-date">Return date<input id="vehicle-return-date" type="date" name="return_date" value="{{ request('return_date') }}" data-vehicle-return-date required></label>
                    <label for="vehicle-return-time">Return time
                        <select id="vehicle-return-time" name="return_time" data-vehicle-return-time required>
                            <option value="">Select time</option>
                            @foreach (['08:00' => '8:00 AM', '09:00' => '9:00 AM', '10:00' => '10:00 AM', '11:00' => '11:00 AM', '12:00' => '12:00 PM', '13:00' => '1:00 PM', '14:00' => '2:00 PM', '15:00' => '3:00 PM', '16:00' => '4:00 PM', '17:00' => '5:00 PM', '18:00' => '6:00 PM'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('return_time') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="vehicle-location-field" for="vehicle-pickup-location">Pickup location<input id="vehicle-pickup-location" type="text" name="pickup_location" value="{{ request('pickup_location') }}" placeholder="Panabo City" required></label>
                    <p class="vehicle-date-hint" data-vehicle-date-hint aria-live="polite">Pickup times are shown in Asia/Manila. Past dates and times aren’t available.</p>
                    <p class="vehicle-live-availability" data-vehicle-availability role="status" aria-live="polite">Choose your pickup and return dates and times to check live availability.</p>
                    <button class="button button-primary" type="submit" data-request-vehicle disabled>Request This Vehicle</button>
                </form>
                <p class="vehicle-estimate-note">No payment has been taken. Final details are confirmed by FleetGuard.</p>
            </aside>
        </div>

        @if ($similar->isNotEmpty())
            <section class="similar-vehicles" aria-labelledby="similar-title">
                <div class="similar-heading">
                    <div>
                        <p class="eyebrow">Explore the collection</p>
                        <h2 id="similar-title">You may also like</h2>
                    </div>
                    <p>More considered choices for your next journey.</p>
                </div>
                <div class="similar-grid">
                    @foreach ($similar as $item)
                        <article class="similar-card">
                            <a class="similar-card-image" href="{{ route('fleet.show', $item) }}" aria-label="View {{ $item->name }} details">
                                @if ($item->image_path)<img src="{{ asset($item->image_path) }}" alt="{{ $item->name }}" width="1000" height="650" loading="lazy">@else<span><i class="bi bi-car-front" aria-hidden="true"></i></span>@endif
                            </a>
                            <div class="similar-card-body">
                                <p>{{ strtoupper($item->category) }}</p>
                                <h3><a href="{{ route('fleet.show', $item) }}">{{ $item->name }}</a></h3>
                                <div class="similar-card-footer">
                                    <span>From <strong>₱{{ number_format((float) $item->daily_rate, 2) }}</strong> / day</span>
                                    <a href="{{ route('fleet.show', $item) }}">View details <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
        <div class="details-page-footer">
            <a class="back-to-fleet" href="{{ route('fleet') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to Fleet</a>
            <span>FleetGuard · Panabo City</span>
        </div>
    </main>
@endsection
