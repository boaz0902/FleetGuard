@extends('layouts.admin')

@section('title', 'Rental Management | FleetGuard')
@section('admin-page-title', 'Rental Management')
@section('admin-page-icon', 'key')
@section('admin-breadcrumb', 'Operations')
@section('admin-page-description', 'Track vehicle hand-offs, active rentals, and returns.')

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
        $chips = [
            'search' => filled($filters['search'] ?? null) ? 'Search: '.$filters['search'] : null,
            'status' => isset($filters['status']) ? 'Status: '.($statusLabels[$filters['status']] ?? $filters['status']) : null,
            'pickup_from' => isset($filters['pickup_from']) ? 'Pickup from: '.$filters['pickup_from'] : null,
            'pickup_to' => isset($filters['pickup_to']) ? 'Pickup to: '.$filters['pickup_to'] : null,
        ];
    @endphp

    <main class="admin-rentals" id="main-content">
        <section class="admin-rentals-kpis" aria-label="Rental statistics">
            @foreach ([
                ['Ready for Check-Out', $metrics['ready'], 'key', 'ready', 'Approved bookings whose pickup time has started.'],
                ['Active Rentals', $metrics['active'], 'car-front', 'ongoing', 'Vehicles currently checked out.'],
                ['Due Today', $metrics['due_today'], 'clock', 'due_today', 'Active rentals due before the end of today.'],
                ['Overdue Returns', $metrics['overdue'], 'exclamation-triangle', 'overdue', 'Past the scheduled exclusive return time.'],
                ['Completed This Month', $metrics['completed_month'], 'check2-circle', 'completed', 'Returns recorded this month.'],
                ['Total Rentals Handled', $metrics['handled'], 'journal-check', null, 'Approved, active, and completed rentals.'],
            ] as [$label, $value, $icon, $filter, $description])
                <a class="admin-rental-kpi {{ $filter === 'overdue' ? 'admin-rental-kpi--danger' : '' }}"
                   href="{{ route('admin.rentals.index', $filter ? ['status' => $filter] : []) }}">
                    <span class="admin-rental-kpi-heading">
                        <span>{{ $label }}</span>
                        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                    </span>
                    <strong>{{ number_format($value) }}</strong>
                    <small>{{ $description }}</small>
                </a>
            @endforeach
        </section>

        <section class="admin-rental-summary" aria-label="Rental summary">
            <div class="admin-rental-summary-title">
                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                <span>Rental snapshot</span>
            </div>
            <div><span>Ready for check-out</span><strong>{{ number_format($metrics['summary']['ready']) }}</strong></div>
            <div><span>Active rentals</span><strong>{{ number_format($metrics['summary']['active']) }}</strong></div>
            <div><span>Completed this month</span><strong>{{ number_format($metrics['summary']['completed_month']) }}</strong></div>
        </section>

        <section class="admin-rentals-panel" aria-labelledby="admin-rentals-heading">
            <div class="admin-rentals-panel-heading">
                <div>
                    <p class="admin-rentals-eyebrow">Approved rental lifecycle</p>
                    <h2 id="admin-rentals-heading">Rental directory</h2>
                </div>
                <div class="admin-rentals-panel-actions">
                    <span class="admin-rentals-result-count">
                        @if ($rentals->total() === 0)
                            Showing 0 rentals
                        @else
                            Showing {{ $rentals->firstItem() }}–{{ $rentals->lastItem() }} of {{ $rentals->total() }}
                        @endif
                    </span>
                    <div class="admin-rentals-view-toggle" role="group" aria-label="Rental directory view">
                        <button type="button" data-rental-view-toggle="table" aria-label="Table view" aria-pressed="true">
                            <i class="bi bi-list-ul" aria-hidden="true"></i>
                        </button>
                        <button type="button" data-rental-view-toggle="grid" aria-label="Card view" aria-pressed="false">
                            <i class="bi bi-grid" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <form class="admin-rentals-filters" method="GET" action="{{ route('admin.rentals.index') }}" data-preserve-search-position>
                <label class="admin-rentals-search">
                    <span>Search rentals</span>
                    <span class="admin-rentals-input-wrap">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100"
                               placeholder="Reference, customer, or vehicle" aria-label="Search reference, customer, or vehicle">
                    </span>
                </label>
                <label>
                    <span>Rental status</span>
                    <select name="status" data-admin-submit-on-change aria-label="Filter by rental status">
                        <option value="">All rentals</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Pickup from</span>
                    <input type="date" name="pickup_from" value="{{ $filters['pickup_from'] ?? '' }}" aria-label="Pickup date from">
                </label>
                <label>
                    <span>Pickup to</span>
                    <input type="date" name="pickup_to" value="{{ $filters['pickup_to'] ?? '' }}" aria-label="Pickup date to">
                </label>
                <label>
                    <span>Sort by</span>
                    <select name="sort" aria-label="Sort rentals">
                        <option value="pickup_datetime" @selected(($filters['sort'] ?? 'pickup_datetime') === 'pickup_datetime')>Pickup date</option>
                        <option value="return_datetime" @selected(($filters['sort'] ?? '') === 'return_datetime')>Return date</option>
                        <option value="booking_reference" @selected(($filters['sort'] ?? '') === 'booking_reference')>Reference</option>
                        <option value="customer" @selected(($filters['sort'] ?? '') === 'customer')>Customer</option>
                        <option value="vehicle" @selected(($filters['sort'] ?? '') === 'vehicle')>Vehicle</option>
                        <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Status</option>
                        <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Record date</option>
                    </select>
                </label>
                <label>
                    <span>Order</span>
                    <select name="direction" aria-label="Sort direction">
                        <option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>Ascending</option>
                        <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>Descending</option>
                    </select>
                </label>
                <button class="admin-rentals-filter-submit" type="submit">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    Apply
                </button>
                @if (count(array_filter($chips)))
                    <a class="admin-rentals-clear" href="{{ route('admin.rentals.index') }}">Clear filters</a>
                @endif
            </form>

            @if (count(array_filter($chips)))
                <div class="admin-rentals-chips" aria-label="Active filters">
                    @foreach ($chips as $key => $label)
                        @if ($label)
                            @php $query = request()->query(); unset($query[$key]); @endphp
                            <a class="admin-rental-chip" href="{{ route('admin.rentals.index', $query) }}">
                                <span>{{ $label }}</span>
                                <i class="bi bi-x-circle" aria-hidden="true"></i>
                                <span class="sr-only">Remove filter</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            <div data-rental-view-panel="table">
                @if ($rentals->isNotEmpty())
                    <div class="admin-rentals-table-wrap">
                        <table class="admin-rentals-table">
                            <thead>
                                <tr>
                                    <th scope="col">Booking reference</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Rental period</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Checked out</th>
                                    <th scope="col">Checked in</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rentals as $booking)
                                    <tr>
                                        <td data-label="Booking reference"><a class="admin-rental-reference" href="{{ route('admin.rentals.show', $booking) }}">{{ $booking->booking_reference }}</a></td>
                                        <td data-label="Customer">{{ $booking->customer?->full_name ?? 'Customer unavailable' }}</td>
                                        <td data-label="Vehicle">{{ $booking->vehicle?->name ?? 'Vehicle unavailable' }}</td>
                                        <td data-label="Rental period">
                                            <span>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</span>
                                            <small>to {{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</small>
                                        </td>
                                        <td data-label="Status">
                                            <span class="admin-rental-status admin-rental-status--{{ $booking->rental_status }}">
                                                <i class="bi bi-{{ $statusIcons[$booking->rental_status] ?? 'circle' }}" aria-hidden="true"></i>
                                                {{ $statusLabels[$booking->rental_status] ?? ucfirst($booking->rental_status) }}
                                            </span>
                                            @if ($booking->late_return)
                                                <small class="admin-rental-late-note">Returned late</small>
                                            @endif
                                        </td>
                                        <td data-label="Checked out">{{ $booking->effective_checked_out_at?->timezone('Asia/Manila')->format('M d, g:i A') ?? '—' }}</td>
                                        <td data-label="Checked in">{{ $booking->effective_checked_in_at?->timezone('Asia/Manila')->format('M d, g:i A') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="admin-rentals-empty">
                        <i class="bi bi-key" aria-hidden="true"></i>
                        <h3>No rentals match these filters</h3>
                        <p>Try a different search, status, or pickup date range.</p>
                        <a href="{{ route('admin.rentals.index') }}">Show all rentals</a>
                    </div>
                @endif
            </div>

            <div data-rental-view-panel="grid" hidden>
                @if ($rentals->isNotEmpty())
                    <div class="admin-rentals-grid">
                        @foreach ($rentals as $booking)
                            <article class="admin-rental-card">
                                <div class="admin-rental-card-heading">
                                    <div>
                                        <p class="admin-rentals-eyebrow">{{ $booking->booking_reference }}</p>
                                        <h3>{{ $booking->customer?->full_name ?? 'Customer unavailable' }}</h3>
                                    </div>
                                    <span class="admin-rental-status admin-rental-status--{{ $booking->rental_status }}">
                                        {{ $statusLabels[$booking->rental_status] ?? ucfirst($booking->rental_status) }}
                                    </span>
                                </div>
                                <dl>
                                    <div><dt>Vehicle</dt><dd>{{ $booking->vehicle?->name ?? 'Vehicle unavailable' }}</dd></div>
                                    <div><dt>Pickup</dt><dd>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                                    <div><dt>Return</dt><dd>{{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                                </dl>
                                <a class="admin-rental-open" href="{{ route('admin.rentals.show', $booking) }}">
                                    View rental <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                </a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="admin-rentals-empty">
                        <i class="bi bi-key" aria-hidden="true"></i>
                        <h3>No rentals match these filters</h3>
                        <p>Try a different search, status, or pickup date range.</p>
                        <a href="{{ route('admin.rentals.index') }}">Show all rentals</a>
                    </div>
                @endif
            </div>

            @if ($rentals->hasPages())
                <div class="admin-rentals-pagination">{{ $rentals->links() }}</div>
            @endif
        </section>
    </main>
@endsection
