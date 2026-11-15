@extends('layouts.app')

@section('title', 'My Bookings | FleetGuard')
@section('meta-description', 'Review your FleetGuard booking requests and their latest statuses.')

@section('content')
    @include('partials.public-navigation')

    <main class="customer-dashboard-page customer-bookings-page" id="main-content">
        @include('partials.customer-account-hero', [
            'heroTitle' => 'My Bookings',
            'heroDescription' => 'Your requests and confirmed journeys, all in one place.',
        ])

        <div class="customer-dashboard-content">
            @if (session('status'))
                <p class="customer-status" role="status" aria-live="polite">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="customer-booking-alert" role="alert">{{ $errors->first() }}</p>
            @endif

            @if (! $verified)
                <section class="customer-empty-state">
                    <span class="customer-empty-icon"><i class="bi bi-envelope-exclamation" aria-hidden="true"></i></span>
                    <h2>Verify your email to view bookings.</h2>
                    <p>Your reservation history is private. Verify your email address to continue.</p>
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button class="customer-submit" type="submit">Resend verification link <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
                    </form>
                </section>
            @else
                <section class="customer-account-summary" aria-label="Booking summary">
                    @foreach ([
                        ['label' => 'Upcoming', 'count' => $summary['upcoming'], 'icon' => 'calendar-event'],
                        ['label' => 'Active', 'count' => $summary['active'], 'icon' => 'key'],
                        ['label' => 'Completed', 'count' => $summary['completed'], 'icon' => 'check2-circle'],
                    ] as $metric)
                        <article class="customer-summary-card">
                            <span class="customer-summary-icon"><i class="bi bi-{{ $metric['icon'] }}" aria-hidden="true"></i></span>
                            <span><strong>{{ number_format($metric['count']) }}</strong><small>{{ $metric['label'] }}</small></span>
                        </article>
                    @endforeach
                </section>

                @if ($summary['next_booking'])
                    <section class="customer-next-highlight" aria-label="Next upcoming booking">
                        <span class="customer-next-highlight-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
                        <div>
                            <p>Next upcoming booking</p>
                            <strong>{{ $summary['next_booking']->vehicle->name }}</strong>
                            <span>{{ $summary['next_booking']->pickup_datetime->format('M j, Y · g:i A') }}</span>
                        </div>
                        <a href="{{ route('bookings.show', $summary['next_booking']) }}">View details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    </section>
                @endif

                @php
                    $tabs = [
                        ['key' => 'all', 'label' => 'All', 'count' => $counts->sum()],
                        ['key' => 'pending', 'label' => 'Pending', 'count' => $counts->get('pending', 0)],
                        ['key' => 'approved', 'label' => 'Approved', 'count' => $counts->get('approved', 0)],
                        ['key' => 'ongoing', 'label' => 'Ongoing', 'count' => $counts->get('ongoing', 0)],
                        ['key' => 'completed', 'label' => 'Completed', 'count' => $counts->get('completed', 0)],
                        ['key' => 'closed', 'label' => 'Cancelled / Rejected', 'count' => $counts->get('cancelled', 0) + $counts->get('rejected', 0)],
                    ];
                @endphp

                <nav class="customer-booking-tabs" aria-label="Filter bookings by status">
                    @foreach ($tabs as $tab)
                        <a
                            class="{{ $activeFilter === $tab['key'] ? 'is-active' : '' }}"
                            href="{{ route('account.bookings', $tab['key'] === 'all' ? [] : ['status' => $tab['key']]) }}"
                            @if ($activeFilter === $tab['key']) aria-current="page" @endif
                        >
                            {{ $tab['label'] }} <span>{{ $tab['count'] }}</span>
                        </a>
                    @endforeach
                </nav>

                @if ($bookings->isEmpty())
                    <section class="customer-empty-state">
                        <span class="customer-empty-icon"><i class="bi bi-calendar2-x" aria-hidden="true"></i></span>
                        @if ($counts->sum() === 0)
                            <h2>No bookings yet.</h2>
                            <p>When you send a booking request, you’ll be able to follow its progress here.</p>
                            <a class="customer-submit" href="{{ route('home') }}#fleet">Browse the fleet <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        @else
                            <h2>No bookings in this status.</h2>
                            <p>Choose another status filter to see more of your reservations.</p>
                            <a class="customer-submit" href="{{ route('account.bookings') }}">Show all bookings <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                        @endif
                    </section>
                @else
                    <section class="customer-booking-list" aria-label="Your bookings">
                        @foreach ($bookings as $booking)
                            @php
                                $statusMeta = match ($booking->status) {
                                    'pending' => ['Pending', 'hourglass-split', 'pending'],
                                    'approved' => ['Approved', 'check-circle', 'approved'],
                                    'ongoing' => ['Ongoing', 'key', 'ongoing'],
                                    'completed' => ['Completed', 'check2-circle', 'completed'],
                                    'cancelled' => ['Cancelled', 'x-circle', 'cancelled'],
                                    'rejected' => ['Rejected', 'slash-circle', 'rejected'],
                                    default => [\Illuminate\Support\Str::headline($booking->status), 'info-circle', 'completed'],
                                };
                            @endphp

                            <article class="customer-booking-card">
                                <div class="customer-booking-vehicle">
                                    <span class="customer-booking-image">
                                        <i class="bi bi-car-front" aria-hidden="true"></i>
                                        @if ($booking->vehicle->image_path)
                                            <img src="{{ asset($booking->vehicle->image_path) }}" alt="" loading="lazy" data-booking-vehicle-image>
                                        @endif
                                    </span>
                                    <div class="customer-booking-vehicle-copy">
                                        <p>{{ $booking->vehicle->category }}</p>
                                        <h2>{{ $booking->vehicle->name }}</h2>
                                        <span>{{ $booking->booking_reference }}</span>
                                    </div>
                                </div>

                                <div class="customer-booking-route">
                                    <div>
                                        <span class="customer-route-dot" aria-hidden="true"></span>
                                        <p>Pickup <strong>{{ $booking->pickup_datetime->format('M j, Y · g:i A') }}</strong><small>{{ $booking->pickup_location }}</small></p>
                                    </div>
                                    <span class="customer-route-line" aria-hidden="true"></span>
                                    <div>
                                        <span class="customer-route-dot is-end" aria-hidden="true"></span>
                                        <p>Return <strong>{{ $booking->return_datetime->format('M j, Y · g:i A') }}</strong><small>{{ $booking->return_location }}</small></p>
                                    </div>
                                </div>

                                <div class="customer-booking-card-footer">
                                    @include('partials.customer-booking-status', ['statusMeta' => $statusMeta])
                                    <span class="customer-booking-total"><small>Estimated total</small><strong>₱{{ number_format((float) $booking->estimated_total, 2) }}</strong></span>
                                    <a class="customer-action-link" href="{{ route('bookings.show', $booking) }}">View details <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    @if ($bookings->hasPages())
                        <div class="customer-booking-pagination">{{ $bookings->links() }}</div>
                    @endif
                @endif
            @endif
        </div>
    </main>
@endsection
