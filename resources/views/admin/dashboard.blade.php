@extends('layouts.admin')

@section('title', 'Admin Dashboard | FleetGuard')
@section('admin-page-title', 'Dashboard')
@section('admin-page-icon', 'speedometer2')
@section('admin-breadcrumb', 'Overview')

@section('admin-content')
    <main class="admin-content" id="main-content">
                <section class="admin-page-intro">
                    <div>
                        <p class="admin-eyebrow">Operations overview</p>
                        <h2>Fleet at a glance</h2>
                        <p>Live reservation and vehicle availability snapshot for {{ $today->format('F j, Y') }}.</p>
                    </div>
                    <a class="admin-primary-link" href="{{ route('admin.calendar.index') }}">
                        View availability
                        <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                    </a>
                </section>

                <section class="admin-metrics" aria-label="Key metrics">
                    <article class="admin-metric-card">
                        <div class="admin-metric-heading">
                            <span>Total vehicles</span>
                            <span class="admin-metric-icon metric-icon--navy" aria-hidden="true">
                                <i class="bi bi-car-front" aria-hidden="true"></i>
                            </span>
                        </div>
                        <strong>{{ number_format($metrics['total_vehicles']) }}</strong>
                        <small>All registered vehicles</small>
                    </article>
                    <article class="admin-metric-card">
                        <div class="admin-metric-heading">
                            <span>Available vehicles</span>
                            <span class="admin-metric-icon metric-icon--green" aria-hidden="true">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                            </span>
                        </div>
                        <strong>{{ number_format($metrics['available_vehicles']) }}</strong>
                        <small>Available for immediate pickup</small>
                    </article>
                    <article class="admin-metric-card">
                        <div class="admin-metric-heading">
                            <span>Pending reservations</span>
                            <span class="admin-metric-icon metric-icon--amber" aria-hidden="true">
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                            </span>
                        </div>
                        <strong>{{ number_format($metrics['pending_reservations']) }}</strong>
                        <small>Awaiting an admin decision</small>
                    </article>
                    <article class="admin-metric-card">
                        <div class="admin-metric-heading">
                            <span>Active rentals</span>
                            <span class="admin-metric-icon metric-icon--blue" aria-hidden="true">
                                <i class="bi bi-key-fill" aria-hidden="true"></i>
                            </span>
                        </div>
                        <strong>{{ number_format($metrics['active_rentals']) }}</strong>
                        <small>Bookings marked ongoing</small>
                    </article>
                    <article class="admin-metric-card">
                        <div class="admin-metric-heading">
                            <span>Total reservations</span>
                            <span class="admin-metric-icon metric-icon--slate" aria-hidden="true">
                                <i class="bi bi-card-list" aria-hidden="true"></i>
                            </span>
                        </div>
                        <strong>{{ number_format($metrics['total_reservations']) }}</strong>
                        <small>All booking statuses</small>
                    </article>
                </section>

                <div class="admin-overview-grid">
                    <section class="admin-panel admin-activity-panel" aria-labelledby="activity-heading">
                        <div class="admin-panel-heading">
                            <div>
                                <p class="admin-eyebrow">Booking activity</p>
                                <h2 id="activity-heading">Reservations over the last 7 days</h2>
                            </div>
                            <span class="admin-panel-period">7 days</span>
                        </div>
                        @if ($activityHasData)
                            <div class="admin-chart" role="img" aria-label="Reservation counts by day for the last seven days">
                                <div class="admin-chart-grid" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                                <div class="admin-chart-columns" aria-hidden="true">
                                    @foreach ($activityChart as $day)
                                        <div class="admin-chart-column">
                                            <span class="admin-chart-count">{{ $day['count'] }}</span>
                                            <div class="admin-chart-track">
                                                <span class="admin-chart-bar" style="height: {{ $day['height'] }}%"></span>
                                            </div>
                                            <span class="admin-chart-label">{{ $day['date']->format('D') }}</span>
                                            <span class="admin-chart-date">{{ $day['date']->format('M j') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="admin-chart-empty">
                                <span aria-hidden="true">—</span>
                                <p>No reservations were created in the last 7 days.</p>
                            </div>
                        @endif
                    </section>

                    <section class="admin-panel admin-fleet-panel" aria-labelledby="fleet-heading">
                        <div class="admin-panel-heading">
                            <div>
                                <p class="admin-eyebrow">Fleet status</p>
                                <h2 id="fleet-heading">Current vehicle availability</h2>
                            </div>
                            <a class="admin-text-link" href="{{ route('admin.calendar.index') }}">Calendar</a>
                        </div>
                        <ul class="admin-fleet-list">
                            <li>
                                <span class="admin-fleet-label"><i class="fleet-dot fleet-dot--available"></i>Available now</span>
                                <strong>{{ number_format($fleetStatus['available']) }}</strong>
                            </li>
                            <li>
                                <span class="admin-fleet-label"><i class="fleet-dot fleet-dot--reserved"></i>Reserved now</span>
                                <strong>{{ number_format($fleetStatus['reserved']) }}</strong>
                            </li>
                            <li>
                                <span class="admin-fleet-label"><i class="fleet-dot fleet-dot--ongoing"></i>Currently rented</span>
                                <strong>{{ number_format($fleetStatus['ongoing']) }}</strong>
                            </li>
                            <li>
                                <span class="admin-fleet-label"><i class="fleet-dot fleet-dot--maintenance"></i>Maintenance</span>
                                <strong>{{ number_format($fleetStatus['maintenance']) }}</strong>
                            </li>
                            <li>
                                <span class="admin-fleet-label"><i class="fleet-dot fleet-dot--inactive"></i>Inactive</span>
                                <strong>{{ number_format($fleetStatus['inactive']) }}</strong>
                            </li>
                        </ul>
                        <p class="admin-fleet-note">Availability uses the current Manila time and excludes overlapping approved or ongoing bookings and maintenance periods.</p>
                    </section>
                </div>

                <section class="admin-panel admin-reservations-panel" aria-labelledby="recent-reservations-heading">
                    <div class="admin-panel-heading">
                        <div>
                            <p class="admin-eyebrow">Latest updates</p>
                            <h2 id="recent-reservations-heading">Recent reservations</h2>
                        </div>
                        <a class="admin-text-link" href="{{ route('admin.bookings.index') }}">
                            Review reservations
                            <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    <div class="admin-table-wrap">
                        <table class="admin-reservations-table">
                            <thead>
                                <tr>
                                    <th scope="col">Reference</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Pickup</th>
                                    <th scope="col">Return</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentReservations as $reservation)
                                    <tr>
                                        <td data-label="Reference"><span class="admin-booking-reference">{{ $reservation->booking_reference }}</span></td>
                                        <td data-label="Customer"><span class="admin-reservation-customer">{{ $reservation->customer?->full_name ?? 'Customer record' }}</span></td>
                                        <td data-label="Vehicle"><span class="admin-reservation-vehicle">{{ $reservation->vehicle?->name ?? 'Vehicle' }}</span></td>
                                        <td data-label="Pickup"><time class="admin-reservation-date" datetime="{{ $reservation->pickup_datetime->toIso8601String() }}">{{ $reservation->pickup_datetime->timezone('Asia/Manila')->format('M d, Y') }}</time></td>
                                        <td data-label="Return"><time class="admin-reservation-date" datetime="{{ $reservation->return_datetime->toIso8601String() }}">{{ $reservation->return_datetime->timezone('Asia/Manila')->format('M d, Y') }}</time></td>
                                        <td data-label="Status">
                                            <span class="admin-status-badge admin-status--{{ $reservation->status }}">{{ ucfirst($reservation->status) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="admin-table-empty" colspan="6">
                                            <strong>No reservations yet</strong>
                                            <span>New booking requests will appear here when customers submit them.</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                <footer class="admin-page-footer">FleetGuard Operations <span>·</span> Dashboard data shown in Asia/Manila time</footer>
    </main>
@endsection
