@extends('layouts.admin')

@section('title', $vehicle->name . ' | FleetGuard')
@section('admin-page-title', 'Vehicle details')
@section('admin-page-icon', 'car-front')
@section('admin-breadcrumb', 'Fleet')
@section('admin-page-description', 'Review vehicle specifications, current availability, reservations, and service history.')

@section('admin-content')
    @php
        $availabilityLabels = [
            'available' => ['Available', 'check-circle'],
            'reserved' => ['Reserved', 'calendar-check'],
            'ongoing' => ['Ongoing rental', 'key'],
            'maintenance' => ['Under maintenance', 'tools'],
            'inactive' => ['Inactive', 'slash-circle'],
        ];
        [$availabilityLabel, $availabilityIcon] = $availabilityLabels[$availabilityStatus] ?? [ucfirst($availabilityStatus), 'question-circle'];
        $vehicleActions = [
            'vehicle_created' => 'Vehicle added',
            'vehicle_updated' => 'Vehicle information updated',
            'vehicle_activated' => 'Vehicle activated',
            'vehicle_deactivated' => 'Vehicle deactivated',
            'vehicle_status_changed' => 'Vehicle status changed',
        ];
        $maintenanceActions = [
            'vehicle_unavailability_created' => 'Maintenance period added',
            'vehicle_unavailability_removed' => 'Maintenance period removed',
        ];
        $maintenanceNow = now('Asia/Manila');
    @endphp

    <main class="admin-fleet-details" id="main-content">
        @if (session('status'))
            <div class="admin-fleet-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="admin-fleet-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="admin-fleet-details-header">
            <a class="admin-fleet-secondary-link" href="{{ route('admin.fleet.index') }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <span>Back to fleet</span>
            </a>
            <div class="admin-fleet-vehicle-hero admin-section-card">
                <span class="admin-fleet-hero-image">
                    @if ($vehicle->image_path)
                        <img src="{{ asset($vehicle->image_path) }}" alt="" loading="lazy">
                    @else
                        <i class="bi bi-car-front" aria-hidden="true"></i>
                    @endif
                </span>
                <div class="admin-fleet-hero-copy">
                    <p class="admin-fleet-eyebrow">{{ strtoupper($vehicle->category) }} · {{ $vehicle->brand }} {{ $vehicle->model }}</p>
                    <h2>{{ $vehicle->name }}</h2>
                    <div class="admin-fleet-hero-badges">
                        <span class="admin-fleet-status admin-fleet-status--{{ $vehicle->status }}">
                            <i class="bi bi-circle-fill" aria-hidden="true"></i>
                            {{ ucfirst($vehicle->status) }}
                        </span>
                        <span class="admin-fleet-availability admin-fleet-availability--{{ $availabilityStatus }}">
                            <i class="bi bi-{{ $availabilityIcon }}" aria-hidden="true"></i>
                            {{ $availabilityLabel }}
                        </span>
                    </div>
                </div>
                <div class="admin-fleet-hero-actions">
                    @can('update', $vehicle)
                        <a class="admin-fleet-primary-link" href="{{ route('admin.fleet.edit', $vehicle) }}">
                            <i class="bi bi-pencil" aria-hidden="true"></i>
                            <span>Edit vehicle</span>
                        </a>
                        <a class="admin-fleet-secondary-link" href="{{ route('admin.fleet.edit', $vehicle) }}">
                            <i class="bi bi-toggles" aria-hidden="true"></i>
                            <span>Manage status</span>
                        </a>
                    @endcan
                    @can('manage', \App\Models\Vehicle::class)
                        <a class="admin-fleet-secondary-link" href="{{ route('admin.fleet.index', ['maintenance_vehicle' => $vehicle->id]) }}#fleet-maintenance-heading">
                            <i class="bi bi-calendar-plus" aria-hidden="true"></i>
                            <span>Add maintenance</span>
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        <section class="admin-fleet-stat-strip" aria-label="Vehicle statistics">
            <article class="admin-fleet-stat-card">
                <span class="admin-fleet-stat-icon"><i class="bi bi-cash-coin" aria-hidden="true"></i></span>
                <div><small>Daily rate</small><strong>₱{{ number_format((float) $vehicle->daily_rate, 2) }}</strong></div>
            </article>
            @if ($canReviewBookings)
                <article class="admin-fleet-stat-card">
                    <span class="admin-fleet-stat-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                    <div><small>Total bookings</small><strong>{{ number_format((int) $totalBookingCount) }}</strong></div>
                </article>
                <article class="admin-fleet-stat-card">
                    <span class="admin-fleet-stat-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                    <div><small>Upcoming approved</small><strong>{{ number_format((int) $upcomingBookingCount) }}</strong></div>
                </article>
            @endif
            <article class="admin-fleet-stat-card">
                <span class="admin-fleet-stat-icon"><i class="bi bi-tools" aria-hidden="true"></i></span>
                <div><small>Maintenance records</small><strong>{{ number_format($maintenanceRecords->count()) }}</strong></div>
            </article>
        </section>

        <nav class="admin-fleet-tabs" data-fleet-tabs aria-label="Vehicle details sections">
            <button type="button" id="fleet-tab-overview" data-fleet-tab="overview" aria-controls="fleet-panel-overview" aria-selected="true">Overview</button>
            @if ($canReviewBookings)
                <button type="button" id="fleet-tab-bookings" data-fleet-tab="bookings" aria-controls="fleet-panel-bookings" aria-selected="false" tabindex="-1">Bookings</button>
            @endif
            <button type="button" id="fleet-tab-maintenance" data-fleet-tab="maintenance" aria-controls="fleet-panel-maintenance" aria-selected="false" tabindex="-1">Maintenance</button>
            <button type="button" id="fleet-tab-activity" data-fleet-tab="activity" aria-controls="fleet-panel-activity" aria-selected="false" tabindex="-1">Activity</button>
        </nav>

        <section class="admin-fleet-tab-panel" id="fleet-panel-overview" data-fleet-tab-panel="overview" aria-labelledby="fleet-tab-overview">
            <div class="admin-fleet-details-grid">
                <section class="admin-fleet-detail-card admin-section-card" aria-labelledby="vehicle-specifications-heading">
                    <div class="admin-fleet-detail-heading">
                        <span class="admin-fleet-detail-icon"><i class="bi bi-card-list" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-fleet-eyebrow">Inventory</p>
                            <h2 id="vehicle-specifications-heading">Vehicle specifications</h2>
                        </div>
                    </div>
                    <dl class="admin-fleet-detail-list">
                        <div><dt>Vehicle name</dt><dd>{{ $vehicle->name }}</dd></div>
                        <div><dt>Brand / model</dt><dd>{{ $vehicle->brand }} {{ $vehicle->model }}</dd></div>
                        <div><dt>Category</dt><dd>{{ strtoupper($vehicle->category) }}</dd></div>
                        <div><dt>Passengers</dt><dd>{{ $vehicle->passenger_capacity }}</dd></div>
                        <div><dt>Transmission</dt><dd>{{ $vehicle->transmission }}</dd></div>
                        <div><dt>Fuel type</dt><dd>{{ $vehicle->fuel_type }}</dd></div>
                        <div><dt>Date added</dt><dd>{{ $vehicle->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                        <div class="admin-fleet-detail-full"><dt>Description</dt><dd>{{ $vehicle->description ?: 'No description provided.' }}</dd></div>
                    </dl>
                </section>

                <section class="admin-fleet-detail-card admin-section-card" aria-labelledby="vehicle-rate-heading">
                    <div class="admin-fleet-detail-heading">
                        <span class="admin-fleet-detail-icon"><i class="bi bi-cash-coin" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-fleet-eyebrow">Rental pricing</p>
                            <h2 id="vehicle-rate-heading">Current daily rate</h2>
                        </div>
                    </div>
                    <strong class="admin-fleet-rate-large">₱{{ number_format((float) $vehicle->daily_rate, 2) }}</strong>
                    <p class="admin-fleet-rate-note">Existing reservations retain their stored daily-rate snapshots and totals.</p>
                </section>
            </div>
        </section>

        @if ($canReviewBookings)
            <section class="admin-fleet-tab-panel" id="fleet-panel-bookings" data-fleet-tab-panel="bookings" aria-labelledby="fleet-tab-bookings">
                <div class="admin-fleet-detail-card admin-section-card">
                    <div class="admin-fleet-detail-heading">
                        <span class="admin-fleet-detail-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-fleet-eyebrow">Confirmed schedule</p>
                            <h2>Upcoming and ongoing reservations</h2>
                        </div>
                    </div>
                    @forelse ($upcomingBookings as $booking)
                        <article class="admin-fleet-history-row">
                            <div>
                                <strong>{{ $booking->booking_reference }}</strong>
                                <small>
                                    {{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    {{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                </small>
                            </div>
                            <span class="admin-status-badge admin-status--{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                        </article>
                    @empty
                        <div class="admin-fleet-empty-inline">
                            <i class="bi bi-calendar2-check" aria-hidden="true"></i>
                            <span>No upcoming approved or ongoing reservations.</span>
                        </div>
                    @endforelse
                </div>

                <div class="admin-fleet-detail-card admin-section-card">
                    <div class="admin-fleet-detail-heading">
                        <span class="admin-fleet-detail-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-fleet-eyebrow">Reservation log</p>
                            <h2>Booking history</h2>
                        </div>
                    </div>
                    @forelse ($bookingHistory as $booking)
                        <article class="admin-fleet-history-row">
                            <div>
                                <strong>{{ $booking->booking_reference }}</strong>
                                <small>
                                    {{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    {{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                </small>
                            </div>
                            <span class="admin-status-badge admin-status--{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                        </article>
                    @empty
                        <div class="admin-fleet-empty-inline">
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                            <span>No bookings are recorded for this vehicle.</span>
                        </div>
                    @endforelse
                </div>
            </section>
        @endif

        <section class="admin-fleet-tab-panel" id="fleet-panel-maintenance" data-fleet-tab-panel="maintenance" aria-labelledby="fleet-tab-maintenance">
            <div class="admin-fleet-detail-card admin-section-card">
                <div class="admin-fleet-detail-heading">
                    <span class="admin-fleet-detail-icon"><i class="bi bi-tools" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-fleet-eyebrow">Service schedule</p>
                        <h2>Maintenance timeline</h2>
                    </div>
                </div>
                @forelse ($maintenanceRecords as $record)
                    @php
                        $maintenanceState = $record->start_datetime > $maintenanceNow
                            ? 'scheduled'
                            : ($record->end_datetime > $maintenanceNow ? 'in_progress' : 'completed');
                        $maintenanceCreator = $maintenanceCreators->get($record->id);
                    @endphp
                    <article class="admin-fleet-timeline-item">
                        <span class="admin-fleet-timeline-marker" aria-hidden="true"><i class="bi bi-tools"></i></span>
                        <div class="admin-fleet-timeline-content">
                            <div class="admin-fleet-timeline-heading">
                                <strong>{{ $record->reason }}</strong>
                                <span class="admin-fleet-availability admin-fleet-availability--{{ $maintenanceState }}">{{ ucfirst(str_replace('_', ' ', $maintenanceState)) }}</span>
                            </div>
                            <small>
                                {{ $record->start_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                {{ $record->end_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                            </small>
                            <small>Created by {{ $maintenanceCreator?->user?->name ?? 'Not recorded' }}</small>
                        </div>
                    </article>
                @empty
                    <div class="admin-fleet-empty-inline">
                        <i class="bi bi-calendar2-check" aria-hidden="true"></i>
                        <span>No maintenance periods are recorded for this vehicle.</span>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="admin-fleet-tab-panel" id="fleet-panel-activity" data-fleet-tab-panel="activity" aria-labelledby="fleet-tab-activity">
            <div class="admin-fleet-detail-card admin-section-card">
                <div class="admin-fleet-detail-heading">
                    <span class="admin-fleet-detail-icon"><i class="bi bi-clipboard-data" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-fleet-eyebrow">Audit trail</p>
                        <h2>Fleet and maintenance activity</h2>
                    </div>
                </div>
                @forelse ($vehicleHistory->concat($maintenanceHistory)->sortByDesc('created_at') as $entry)
                    @php
                        $changes = $entry->changes ?? [];
                        $action = $vehicleActions[$entry->action] ?? $maintenanceActions[$entry->action] ?? ucfirst(str_replace('_', ' ', $entry->action));
                    @endphp
                    <article class="admin-fleet-history-row admin-fleet-audit-row">
                        <div>
                            <strong>{{ $action }}</strong>
                            @foreach ($changes as $field => $change)
                                @if (is_array($change) && array_key_exists('from', $change) && array_key_exists('to', $change))
                                    <small>{{ ucfirst(str_replace('_', ' ', $field)) }}: {{ $change['from'] ?? '—' }} <i class="bi bi-arrow-right" aria-hidden="true"></i> {{ $change['to'] ?? '—' }}</small>
                                @endif
                            @endforeach
                            @if (isset($changes['reason']))
                                <small>Reason: {{ $changes['reason'] }}</small>
                            @endif
                        </div>
                        <span>{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</span>
                    </article>
                @empty
                    <div class="admin-fleet-empty-inline">
                        <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                        <span>No fleet or maintenance audit events are recorded yet.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </main>
@endsection
