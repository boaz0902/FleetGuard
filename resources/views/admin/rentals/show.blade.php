@extends('layouts.admin')

@section('title', $booking->booking_reference . ' | Rentals | FleetGuard')
@section('admin-page-title', 'Rental Details')
@section('admin-page-icon', 'key')
@section('admin-breadcrumb', 'Operations')
@section('admin-page-description', 'Review the rental hand-off, return, linked records, and audit timeline.')

@section('admin-content')
    @php
        $statusLabels = [
            'ready' => 'Ready for Check-Out',
            'scheduled' => 'Scheduled',
            'ongoing' => 'Ongoing',
            'due_today' => 'Due Today',
            'overdue' => 'Overdue',
            'completed' => 'Completed',
        ];
        $statusIcons = [
            'ready' => 'key',
            'scheduled' => 'calendar-event',
            'ongoing' => 'car-front',
            'due_today' => 'clock',
            'overdue' => 'exclamation-triangle',
            'completed' => 'check2-circle',
        ];
        $checkedOut = $booking->auditLogs->first(fn ($log) => in_array($log->action, ['rental_checked_out', 'booking_marked_ongoing'], true));
        $checkedIn = $booking->auditLogs->first(fn ($log) => in_array($log->action, ['rental_checked_in', 'booking_marked_completed'], true));
    @endphp

    <main class="admin-rental-details" id="main-content">
        @if (session('status'))
            <div class="admin-rental-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="admin-rental-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <a class="admin-rental-back" href="{{ route('admin.rentals.index') }}">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to rentals</span>
        </a>

        <section class="admin-rental-hero" aria-label="Rental summary">
            <div class="admin-rental-avatar" aria-hidden="true"><i class="bi bi-key"></i></div>
            <div class="admin-rental-hero-copy">
                <p class="admin-rentals-eyebrow">Rental record · Booking #{{ $booking->id }}</p>
                <h2>{{ $booking->booking_reference }}</h2>
                <span class="admin-rental-status admin-rental-status--{{ $rental_status }}">
                    <i class="bi bi-{{ $statusIcons[$rental_status] ?? 'circle' }}" aria-hidden="true"></i>
                    {{ $statusLabels[$rental_status] ?? ucfirst($rental_status) }}
                </span>
                @if ($late_return)
                    <span class="admin-rental-status admin-rental-status--overdue">Returned late</span>
                @endif
            </div>
            <div class="admin-rental-hero-actions">
                <a class="admin-rental-secondary" href="{{ route('admin.bookings.show', $booking) }}">
                    <i class="bi bi-journal-check" aria-hidden="true"></i>
                    Reservation
                </a>
                @if ($canManageRental && $booking->status === \App\Models\Booking::STATUS_APPROVED)
                    @if ($checkout_block_reason)
                        <p class="admin-rental-action-note" role="status">{{ $checkout_block_reason }}</p>
                    @else
                        <button class="admin-rental-primary" type="button" data-rental-dialog-open="rental-checkout-dialog">
                            <i class="bi bi-key" aria-hidden="true"></i>
                            Check out vehicle
                        </button>
                    @endif
                @elseif ($canManageRental && $booking->status === \App\Models\Booking::STATUS_ONGOING)
                    <button class="admin-rental-primary" type="button" data-rental-dialog-open="rental-checkin-dialog">
                        <i class="bi bi-box-arrow-in-left" aria-hidden="true"></i>
                        Check in vehicle
                    </button>
                @endif
            </div>
        </section>

        <section class="admin-rental-stat-strip" aria-label="Rental summary">
            <article><span>Total</span><strong>₱{{ number_format((float) $booking->estimated_total, 2) }}</strong><small>Stored booking snapshot</small></article>
            <article><span>Daily rate</span><strong>₱{{ number_format((float) $booking->daily_rate_snapshot, 2) }}</strong><small>{{ $booking->rental_duration_days }} rental days</small></article>
            <article><span>Checked out</span><strong>{{ $checkedOut?->created_at?->timezone('Asia/Manila')->format('M d, g:i A') ?? 'Not yet' }}</strong><small>{{ $checkedOut?->user?->name ?? 'Awaiting release' }}</small></article>
            <article><span>Checked in</span><strong>{{ $checkedIn?->created_at?->timezone('Asia/Manila')->format('M d, g:i A') ?? 'Not yet' }}</strong><small>{{ $checkedIn?->user?->name ?? 'Awaiting return' }}</small></article>
        </section>

        <section class="admin-rental-content" aria-label="Rental information">
            <nav class="admin-rental-tabs" data-fleet-tabs aria-label="Rental details sections">
                <button type="button" id="rental-tab-overview" data-fleet-tab="overview" aria-controls="rental-panel-overview" aria-selected="true">Overview</button>
                <button type="button" id="rental-tab-timeline" data-fleet-tab="timeline" aria-controls="rental-panel-timeline" aria-selected="false" tabindex="-1">Timeline</button>
                <button type="button" id="rental-tab-customer" data-fleet-tab="customer" aria-controls="rental-panel-customer" aria-selected="false" tabindex="-1">Customer</button>
                <button type="button" id="rental-tab-vehicle" data-fleet-tab="vehicle" aria-controls="rental-panel-vehicle" aria-selected="false" tabindex="-1">Vehicle</button>
            </nav>

            <section class="admin-rental-tab-panel" id="rental-panel-overview" data-fleet-tab-panel="overview" aria-labelledby="rental-tab-overview">
                <div class="admin-rental-detail-grid">
                    <article class="admin-rental-detail-card">
                        <p class="admin-rentals-eyebrow">Booking snapshot</p>
                        <h3>Rental period and pricing</h3>
                        <dl>
                            <div><dt>Pickup</dt><dd>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                            <div><dt>Pickup location</dt><dd>{{ $booking->pickup_location }}</dd></div>
                            <div><dt>Return</dt><dd>{{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                            <div><dt>Return location</dt><dd>{{ $booking->return_location }}</dd></div>
                            <div><dt>Duration</dt><dd>{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</dd></div>
                            <div><dt>Daily rate snapshot</dt><dd>₱{{ number_format((float) $booking->daily_rate_snapshot, 2) }}</dd></div>
                            <div><dt>Estimated total snapshot</dt><dd>₱{{ number_format((float) $booking->estimated_total, 2) }}</dd></div>
                        </dl>
                    </article>
                    <article class="admin-rental-detail-card">
                        <p class="admin-rentals-eyebrow">Current state</p>
                        <h3>Rental status</h3>
                        <p class="admin-rental-status-copy">
                            <span class="admin-rental-status admin-rental-status--{{ $rental_status }}">
                                {{ $statusLabels[$rental_status] ?? ucfirst($rental_status) }}
                            </span>
                        </p>
                        <p>Booking dates and rate snapshots remain unchanged during check-out and check-in.</p>
                        @if ($rental_status === 'overdue')
                            <p class="admin-rental-overdue-message"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> The scheduled exclusive return time has passed.</p>
                        @elseif ($late_return)
                            <p class="admin-rental-overdue-message">The vehicle was returned after the scheduled return time.</p>
                        @endif
                    </article>
                </div>
            </section>

            <section class="admin-rental-tab-panel" id="rental-panel-timeline" data-fleet-tab-panel="timeline" aria-labelledby="rental-tab-timeline" hidden>
                <article class="admin-rental-detail-card">
                    <p class="admin-rentals-eyebrow">Recorded events</p>
                    <h3>Rental timeline</h3>
                    <ol class="admin-rental-timeline">
                        @forelse ($timeline as $entry)
                            <li>
                                <span class="admin-rental-timeline-marker" aria-hidden="true"></span>
                                <div>
                                    <strong>{{ $entry['label'] }}</strong>
                                    <time datetime="{{ $entry['created_at']?->toIso8601String() }}">{{ $entry['created_at']?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</time>
                                    <small>By {{ $entry['actor'] }}</small>
                                    @if (($entry['changes']['overdue'] ?? false) === true)
                                        <small class="admin-rental-overdue-message">Returned after the scheduled time.</small>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="admin-rental-timeline-empty">No workflow events are available for this booking.</li>
                        @endforelse
                    </ol>
                </article>
            </section>

            <section class="admin-rental-tab-panel" id="rental-panel-customer" data-fleet-tab-panel="customer" aria-labelledby="rental-tab-customer" hidden>
                <article class="admin-rental-detail-card">
                    <p class="admin-rentals-eyebrow">Linked customer</p>
                    <h3>{{ $booking->customer?->full_name ?? 'Customer unavailable' }}</h3>
                    @if ($booking->customer)
                        <dl>
                            <div><dt>Email</dt><dd>{{ $booking->customer->display_email }}</dd></div>
                            <div><dt>Phone</dt><dd>{{ $booking->customer->display_phone }}</dd></div>
                        </dl>
                        <a class="admin-rental-secondary" href="{{ route('admin.customers.show', $booking->customer) }}">View customer record <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    @endif
                </article>
            </section>

            <section class="admin-rental-tab-panel" id="rental-panel-vehicle" data-fleet-tab-panel="vehicle" aria-labelledby="rental-tab-vehicle" hidden>
                <article class="admin-rental-detail-card">
                    <p class="admin-rentals-eyebrow">Linked fleet vehicle</p>
                    <h3>{{ $booking->vehicle?->name ?? 'Vehicle unavailable' }}</h3>
                    @if ($booking->vehicle)
                        <dl>
                            <div><dt>Category</dt><dd>{{ ucfirst($booking->vehicle->category) }}</dd></div>
                            <div><dt>Current vehicle state</dt><dd>{{ ucfirst($booking->vehicle->status) }}</dd></div>
                        </dl>
                        <a class="admin-rental-secondary" href="{{ route('admin.fleet.show', $booking->vehicle) }}">View fleet record <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                    @endif
                </article>
            </section>
        </section>

        @if ($canManageRental && $booking->status === \App\Models\Booking::STATUS_APPROVED && ! $checkout_block_reason)
            <dialog class="admin-rental-dialog" id="rental-checkout-dialog" aria-labelledby="rental-checkout-title">
                <div class="admin-rental-dialog-heading">
                    <span class="admin-rental-dialog-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                    <button type="button" class="admin-rental-dialog-close" data-rental-dialog-close aria-label="Close check-out confirmation">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <h2 id="rental-checkout-title">Confirm vehicle check-out</h2>
                <p>Confirm that the vehicle is being released to the customer.</p>
                <dl>
                    <div><dt>Reference</dt><dd>{{ $booking->booking_reference }}</dd></div>
                    <div><dt>Customer</dt><dd>{{ $booking->customer?->full_name }}</dd></div>
                    <div><dt>Vehicle</dt><dd>{{ $booking->vehicle?->name }}</dd></div>
                    <div><dt>Rental period</dt><dd>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, g:i A') }} – {{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, g:i A') }}</dd></div>
                </dl>
                <form method="POST" action="{{ route('admin.rentals.checkout', $booking) }}" data-admin-disable-submit>
                    @csrf
                    <div class="admin-rental-dialog-actions">
                        <button class="admin-rental-secondary" type="button" data-rental-dialog-close>Not yet</button>
                        <button class="admin-rental-primary" type="submit">Release vehicle</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($canManageRental && $booking->status === \App\Models\Booking::STATUS_ONGOING)
            <dialog class="admin-rental-dialog" id="rental-checkin-dialog" aria-labelledby="rental-checkin-title">
                <div class="admin-rental-dialog-heading">
                    <span class="admin-rental-dialog-icon"><i class="bi bi-box-arrow-in-left" aria-hidden="true"></i></span>
                    <button type="button" class="admin-rental-dialog-close" data-rental-dialog-close aria-label="Close check-in confirmation">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <h2 id="rental-checkin-title">Confirm vehicle check-in</h2>
                <p>Record the vehicle’s return and complete this rental.</p>
                <dl>
                    <div><dt>Reference</dt><dd>{{ $booking->booking_reference }}</dd></div>
                    <div><dt>Customer</dt><dd>{{ $booking->customer?->full_name }}</dd></div>
                    <div><dt>Vehicle</dt><dd>{{ $booking->vehicle?->name }}</dd></div>
                    <div><dt>Scheduled return</dt><dd>{{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, g:i A') }}</dd></div>
                </dl>
                <form method="POST" action="{{ route('admin.rentals.checkin', $booking) }}" data-admin-disable-submit>
                    @csrf
                    <div class="admin-rental-dialog-actions">
                        <button class="admin-rental-secondary" type="button" data-rental-dialog-close>Not yet</button>
                        <button class="admin-rental-primary" type="submit">Receive vehicle</button>
                    </div>
                </form>
            </dialog>
        @endif
    </main>
@endsection
