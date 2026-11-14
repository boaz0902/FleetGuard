@extends('layouts.admin')

@section('title', 'Reservations | FleetGuard')
@section('admin-page-title', 'Reservations')
@section('admin-page-icon', 'calendar2-check')
@section('admin-breadcrumb', 'Operations')
@section('admin-page-description', 'Search and review customer reservation requests.')

@section('admin-content')
    @php
        $statusIcons = [
            \App\Models\Booking::STATUS_PENDING => 'hourglass-split',
            \App\Models\Booking::STATUS_APPROVED => 'check-circle',
            \App\Models\Booking::STATUS_REJECTED => 'x-circle',
            \App\Models\Booking::STATUS_ONGOING => 'car-front',
            \App\Models\Booking::STATUS_COMPLETED => 'check2-circle',
            \App\Models\Booking::STATUS_CANCELLED => 'slash-circle',
        ];
    @endphp

    <main class="admin-bookings" id="main-content">
        @if (session('status'))
            <div class="admin-bookings-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <section class="admin-booking-metrics" aria-label="Reservation totals">
            <article class="admin-booking-metric admin-section-card">
                <div class="admin-bookings-metric-heading">
                    <span>Total reservations</span>
                    <i class="bi bi-calendar2-check metric-icon--navy" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format((int) $metrics['total']) }}</strong>
                <small>Across all statuses</small>
            </article>
            <article class="admin-booking-metric admin-section-card">
                <div class="admin-bookings-metric-heading">
                    <span>Pending</span>
                    <i class="bi bi-hourglass-split metric-icon--amber" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format((int) $metrics['pending']) }}</strong>
                <small>Awaiting review</small>
            </article>
            <article class="admin-booking-metric admin-section-card">
                <div class="admin-bookings-metric-heading">
                    <span>Approved</span>
                    <i class="bi bi-check-circle metric-icon--blue" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format((int) $metrics['approved']) }}</strong>
                <small>Confirmed reservations</small>
            </article>
            <article class="admin-booking-metric admin-section-card">
                <div class="admin-bookings-metric-heading">
                    <span>Ongoing</span>
                    <i class="bi bi-car-front metric-icon--green" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format((int) $metrics['ongoing']) }}</strong>
                <small>Currently on the road</small>
            </article>
            <article class="admin-booking-metric admin-section-card">
                <div class="admin-bookings-metric-heading">
                    <span>Completed</span>
                    <i class="bi bi-check2-circle metric-icon--slate" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format((int) $metrics['completed']) }}</strong>
                <small>Finished rentals</small>
            </article>
        </section>

        <section class="admin-bookings-panel admin-section-card" aria-labelledby="reservation-list-heading">
            <div class="admin-bookings-panel-heading">
                <div>
                    <p class="admin-bookings-eyebrow">Reservation register</p>
                    <h2 id="reservation-list-heading">All reservations</h2>
                </div>
                <span class="admin-bookings-result-count">{{ number_format($bookings->total()) }} results</span>
            </div>

            <form class="admin-bookings-filters" method="GET" action="{{ route('admin.bookings.index') }}" data-preserve-search-position>
                <label class="admin-bookings-search">
                    <span>Search reservations</span>
                    <span class="admin-bookings-input-wrap">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            maxlength="100"
                            placeholder="Reference, customer, vehicle, dates/times, duration, total, or status"
                            aria-label="Search reservations by reference, customer, vehicle, pickup or return date or time, duration, total, status, or created date or time"
                        >
                    </span>
                    <small class="admin-search-hint">Press Enter to search</small>
                </label>
            </form>

            @if ($errors->any())
                <div class="admin-bookings-validation" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <div class="admin-bookings-table-wrap">
                    <table class="admin-bookings-table">
                        <thead>
                            <tr>
                                <th scope="col">Reference</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Pickup</th>
                                <th scope="col">Return</th>
                                <th scope="col">Duration</th>
                                <th scope="col">Total</th>
                                <th scope="col">Status</th>
                                <th scope="col">Created</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bookings as $booking)
                                <tr>
                                    <td data-label="Reference">
                                        <span class="admin-booking-reference">{{ $booking->booking_reference }}</span>
                                    </td>
                                    <td data-label="Customer">{{ $booking->customer?->full_name ?? 'Customer record' }}</td>
                                    <td data-label="Vehicle">
                                        <span>{{ $booking->vehicle?->name ?? 'Vehicle' }}</span>
                                        @if ($booking->vehicle?->category)
                                            <small>{{ ucfirst($booking->vehicle->category) }}</small>
                                        @endif
                                    </td>
                                    <td data-label="Pickup">
                                        <span class="admin-bookings-date">{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y') }}</span>
                                        <small>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('g:i A') }}</small>
                                    </td>
                                    <td data-label="Return">
                                        <span class="admin-bookings-date">{{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y') }}</span>
                                        <small>{{ $booking->return_datetime?->timezone('Asia/Manila')->format('g:i A') }}</small>
                                    </td>
                                    <td data-label="Duration">{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</td>
                                    <td data-label="Total" class="admin-bookings-estimate">₱{{ number_format((float) $booking->estimated_total, 2) }}</td>
                                    <td data-label="Status">
                                        <span class="admin-status-badge admin-status--{{ $booking->status }}">
                                            <i class="bi bi-{{ $statusIcons[$booking->status] ?? 'question-circle' }}" aria-hidden="true"></i>
                                            {{ ucfirst($booking->status) }}
                                        </span>
                                    </td>
                                    <td data-label="Created">
                                        <span class="admin-bookings-date">{{ $booking->created_at?->timezone('Asia/Manila')->format('M d, Y') }}</span>
                                        <small>{{ $booking->created_at?->timezone('Asia/Manila')->format('g:i A') }}</small>
                                    </td>
                                    <td data-label="Action">
                                        <a class="admin-bookings-view" href="{{ route('admin.bookings.show', ['booking' => $booking] + request()->query()) }}" aria-label="Manage {{ $booking->booking_reference }}">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                            <span>Manage</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="admin-bookings-empty" colspan="10">
                                        <i class="bi bi-calendar2-x" aria-hidden="true"></i>
                                        <strong>{{ request()->filled('search') ? 'No matching reservations' : 'No reservations yet' }}</strong>
                                        <span>{{ request()->filled('search') ? 'Try another search.' : 'Reservations will appear here when customers submit a request.' }}</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            @if ($bookings->hasPages())
                <div class="admin-bookings-pagination">{{ $bookings->links() }}</div>
            @endif
        </section>
    </main>
@endsection
