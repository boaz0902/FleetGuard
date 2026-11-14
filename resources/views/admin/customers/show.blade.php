@extends('layouts.admin')

@section('title', $customer->full_name.' | Customers | FleetGuard')
@section('admin-page-title', 'Customer record')
@section('admin-page-icon', 'person-vcard')
@section('admin-breadcrumb', 'Customers')
@section('admin-page-description', 'Review profile details, rental history, and customer activity.')

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
        $actionLabels = [
            'customer_updated' => 'Customer profile updated',
            'booking_submitted' => 'Reservation submitted',
            'booking_approved' => 'Reservation approved',
            'booking_rejected' => 'Reservation rejected',
            'booking_cancelled' => 'Reservation cancelled',
            'booking_marked_ongoing' => 'Rental marked ongoing',
            'booking_marked_completed' => 'Rental marked completed',
            'booking_status_changed' => 'Reservation status changed',
        ];
    @endphp

    <main class="admin-customer-details {{ $canEdit ? 'admin-customer-details--editable' : '' }}" id="main-content">
        @if (session('status'))
            <div class="admin-customers-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="admin-customer-details-header">
            <a class="admin-customer-back" href="{{ route('admin.customers.index') }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to customers</span>
            </a>
            <section class="admin-customer-profile-banner admin-section-card" aria-label="Customer summary">
                <span class="admin-customer-avatar admin-customer-avatar--large" aria-hidden="true">{{ $customer->initials() }}</span>
                <div class="admin-customer-profile-heading">
                    <p class="admin-customers-eyebrow">Customer #{{ $customer->id }}</p>
                    <h2>{{ $customer->full_name }}</h2>
                    <span>Record created {{ $customer->created_at?->timezone('Asia/Manila')->format('M d, Y') }}</span>
                </div>
                @if ($canEdit)
                    <a class="admin-customer-primary-link admin-customer-banner-action" href="{{ route('admin.customers.edit', $customer) }}">
                        <i class="bi bi-pencil" aria-hidden="true"></i><span>Edit customer</span>
                    </a>
                @endif
            </section>
        </div>

        @if ($canReviewBookings)
            <section class="admin-customer-lifetime-metrics" aria-label="Customer booking summary">
                <article class="admin-customer-lifetime-card admin-section-card">
                    <span>Total bookings</span>
                    <strong>{{ number_format($details['booking_count']) }}</strong>
                    <small>Across all booking statuses</small>
                </article>
                <article class="admin-customer-lifetime-card admin-section-card">
                    <span>Lifetime booking value</span>
                    <strong>₱{{ number_format($details['lifetime_total'], 2) }}</strong>
                    <small>Based on stored booking totals</small>
                </article>
                <article class="admin-customer-lifetime-card admin-section-card">
                    <span>Active / upcoming</span>
                    <strong>{{ number_format($details['current_and_upcoming']->count()) }}</strong>
                    <small>Approved upcoming and ongoing rentals</small>
                </article>
            </section>
        @endif

        <section class="admin-customer-tabs-card admin-section-card" aria-label="Customer details">
            <div class="admin-customer-tabs" data-fleet-tabs aria-label="Customer detail sections">
                <button id="customer-tab-overview" type="button" role="tab" aria-controls="customer-panel-overview" aria-selected="{{ $activeTab === 'overview' ? 'true' : 'false' }}" data-fleet-tab="overview">Overview</button>
                <button id="customer-tab-bookings" type="button" role="tab" aria-controls="customer-panel-bookings" aria-selected="{{ $activeTab === 'bookings' ? 'true' : 'false' }}" data-fleet-tab="bookings">Bookings</button>
                <button id="customer-tab-notes" type="button" role="tab" aria-controls="customer-panel-notes" aria-selected="{{ $activeTab === 'notes' ? 'true' : 'false' }}" data-fleet-tab="notes">Notes</button>
                <button id="customer-tab-activity" type="button" role="tab" aria-controls="customer-panel-activity" aria-selected="{{ $activeTab === 'activity' ? 'true' : 'false' }}" data-fleet-tab="activity">Activity</button>
            </div>

            <div id="customer-panel-overview" role="tabpanel" aria-labelledby="customer-tab-overview" data-fleet-tab-panel="overview" @if ($activeTab !== 'overview') hidden @endif>
                <div class="admin-customer-overview-grid">
                    <section class="admin-customer-detail-card" aria-labelledby="customer-profile-heading">
                        <div class="admin-customer-detail-heading">
                            <span class="admin-customer-detail-icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
                            <div><p class="admin-customers-eyebrow">Profile</p><h3 id="customer-profile-heading">Contact information</h3></div>
                        </div>
                        <dl class="admin-customer-detail-list">
                            <div><dt>Full name</dt><dd>{{ $customer->full_name }}</dd></div>
                            <div><dt>Email</dt><dd>{{ $displayEmail }}</dd></div>
                            <div><dt>Phone</dt><dd>{{ $displayPhone }}</dd></div>
                            <div><dt>Account association</dt><dd>{{ $customer->user_id ? 'Linked account' : 'No linked account' }}</dd></div>
                            <div><dt>Customer record created</dt><dd>{{ $customer->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                        </dl>
                        @if (! $canEdit)
                            <p class="admin-customer-privacy-note"><i class="bi bi-shield-lock" aria-hidden="true"></i>Contact details are masked for view-only access.</p>
                        @endif
                    </section>

                    @if ($canReviewBookings)
                        <section class="admin-customer-detail-card" aria-labelledby="customer-current-bookings-heading">
                            <div class="admin-customer-detail-heading">
                                <span class="admin-customer-detail-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                                <div><p class="admin-customers-eyebrow">Rental schedule</p><h3 id="customer-current-bookings-heading">Current and upcoming</h3></div>
                            </div>
                            @if ($details['current_and_upcoming']->isEmpty())
                                <div class="admin-customer-inline-empty"><i class="bi bi-calendar2-x" aria-hidden="true"></i><span>No current or upcoming bookings.</span></div>
                            @else
                                <ul class="admin-customer-upcoming-list">
                                    @foreach ($details['current_and_upcoming'] as $booking)
                                        <li>
                                            <span class="admin-customer-upcoming-icon"><i class="bi bi-{{ $statusIcons[$booking->status] ?? 'calendar-check' }}" aria-hidden="true"></i></span>
                                            <span class="admin-customer-upcoming-copy">
                                                <a href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->booking_reference }}</a>
                                                <small>{{ $booking->vehicle?->name ?? 'Vehicle' }} · {{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y') }}</small>
                                            </span>
                                            <span class="admin-customer-status admin-customer-status--{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @else
                        <section class="admin-customer-detail-card" aria-label="Booking details restricted">
                            <div class="admin-customer-inline-empty"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Booking details are restricted for your role.</span></div>
                        </section>
                    @endif
                </div>
            </div>

            <div id="customer-panel-bookings" role="tabpanel" aria-labelledby="customer-tab-bookings" data-fleet-tab-panel="bookings" @if ($activeTab !== 'bookings') hidden @endif>
                @if (! $canReviewBookings)
                    <div class="admin-customer-tab-empty">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <strong>Booking history is restricted</strong>
                        <span>Your role does not have permission to review reservation details.</span>
                    </div>
                @else
                    <div class="admin-customer-bookings-heading">
                        <div><p class="admin-customers-eyebrow">Rental history</p><h3>All bookings</h3></div>
                        <span>{{ number_format($details['booking_count']) }} total</span>
                    </div>
                    @if ($details['bookings']->isEmpty())
                    <div class="admin-customer-tab-empty">
                        <i class="bi bi-calendar2-x" aria-hidden="true"></i>
                        <strong>No booking history</strong>
                        <span>Bookings associated with this customer record will appear here.</span>
                    </div>
                    @else
                        <div class="admin-customer-bookings-table-wrap">
                        <table class="admin-customer-bookings-table">
                            <thead><tr><th scope="col">Reference</th><th scope="col">Vehicle</th><th scope="col">Rental period</th><th scope="col">Rate snapshot</th><th scope="col">Stored total</th><th scope="col">Status</th></tr></thead>
                            <tbody>
                                @foreach ($details['bookings'] as $booking)
                                    <tr>
                                        <td data-label="Reference">
                                            <a class="admin-customer-booking-reference" href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->booking_reference }}</a>
                                        </td>
                                        <td data-label="Vehicle">{{ $booking->vehicle?->name ?? 'Vehicle' }}</td>
                                        <td data-label="Rental period">
                                            <span>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y') }}</span>
                                            <small>to {{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y') }}</small>
                                        </td>
                                        <td data-label="Rate snapshot">₱{{ number_format((float) $booking->daily_rate_snapshot, 2) }} / day</td>
                                        <td data-label="Stored total" class="admin-customer-booking-total">₱{{ number_format((float) $booking->estimated_total, 2) }}</td>
                                        <td data-label="Status"><span class="admin-customer-status admin-customer-status--{{ $booking->status }}"><i class="bi bi-{{ $statusIcons[$booking->status] ?? 'question-circle' }}" aria-hidden="true"></i>{{ ucfirst($booking->status) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        @if ($details['bookings']->hasPages())
                            <div class="admin-customers-pagination">{{ $details['bookings']->appends(['tab' => 'bookings'])->links() }}</div>
                        @endif
                    @endif
                @endif
            </div>

            <div id="customer-panel-notes" role="tabpanel" aria-labelledby="customer-tab-notes" data-fleet-tab-panel="notes" @if ($activeTab !== 'notes') hidden @endif>
                <div class="admin-customer-tab-empty admin-customer-notes-deferred">
                    <i class="bi bi-sticky" aria-hidden="true"></i>
                    <strong>Internal notes are deferred</strong>
                    <span>No notes are stored for customer records. A dedicated notes table and its author/timestamp fields require a separate approved migration.</span>
                </div>
            </div>

            <div id="customer-panel-activity" role="tabpanel" aria-labelledby="customer-tab-activity" data-fleet-tab-panel="activity" @if ($activeTab !== 'activity') hidden @endif>
                @if (! $canViewAudit)
                    <div class="admin-customer-tab-empty">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <strong>Activity history is restricted</strong>
                        <span>Your role does not have permission to view customer audit records.</span>
                    </div>
                @elseif ($details['activity']->isEmpty())
                    <div class="admin-customer-tab-empty">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <strong>No recent activity</strong>
                        <span>Customer profile changes and related customer audit records will appear here.</span>
                    </div>
                @else
                    <ol class="admin-customer-activity-list">
                        @foreach ($details['activity'] as $entry)
                            <li>
                                <span class="admin-customer-activity-icon"><i class="bi bi-activity" aria-hidden="true"></i></span>
                                <div>
                                    <strong>{{ $actionLabels[$entry->action] ?? \Illuminate\Support\Str::headline($entry->action) }}</strong>
                                    <span>{{ $entry->user?->name ?? 'System' }}</span>
                                </div>
                                <time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</time>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>

        @if ($canEdit)
            <div class="admin-customer-sticky-actions">
                <a class="admin-customer-secondary-link" href="{{ route('admin.customers.index') }}">Back</a>
                <a class="admin-customer-primary-link" href="{{ route('admin.customers.edit', $customer) }}"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit customer</span></a>
            </div>
        @endif
    </main>
@endsection
