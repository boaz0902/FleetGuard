@extends('layouts.admin')

@section('title', $booking->booking_reference . ' | Reservations | FleetGuard')
@section('admin-page-title', 'Reservation details')
@section('admin-page-icon', 'journal-text')
@section('admin-breadcrumb', 'Operations')
@section('admin-page-description', 'Review the booking, customer, rental details, and status history.')

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
            'booking_submitted' => 'Reservation submitted',
            'booking_approved' => 'Reservation approved',
            'booking_rejected' => 'Reservation rejected',
            'booking_cancelled' => 'Reservation cancelled',
            'booking_marked_ongoing' => 'Rental marked ongoing',
            'booking_marked_completed' => 'Rental marked completed',
            'booking_status_changed' => 'Status changed',
        ];
        $transitionLabels = [
            \App\Models\Booking::STATUS_APPROVED => ['Approve reservation', 'check2-circle', 'admin-booking-action--approve'],
            \App\Models\Booking::STATUS_REJECTED => ['Reject request', 'x-circle', 'admin-booking-action--reject'],
            \App\Models\Booking::STATUS_ONGOING => ['Mark rental ongoing', 'car-front', 'admin-booking-action--ongoing'],
            \App\Models\Booking::STATUS_COMPLETED => ['Mark completed', 'check2-circle', 'admin-booking-action--complete'],
            \App\Models\Booking::STATUS_CANCELLED => ['Cancel reservation', 'slash-circle', 'admin-booking-action--cancel'],
        ];
    @endphp

    <main class="admin-booking-details" id="main-content">
        @if (session('status'))
            <div class="admin-bookings-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="admin-bookings-validation" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="admin-booking-details-header">
            <a class="admin-bookings-back" href="{{ route('admin.bookings.index', request()->query()) }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <span>Back to reservations</span>
            </a>
            <div class="admin-booking-reference-card admin-section-card">
                <div>
                    <p class="admin-bookings-eyebrow">Reservation reference</p>
                    <h2>{{ $booking->booking_reference }}</h2>
                </div>
                <span class="admin-status-badge admin-status--{{ $booking->status }}">
                    <i class="bi bi-{{ $statusIcons[$booking->status] ?? 'question-circle' }}" aria-hidden="true"></i>
                    {{ ucfirst($booking->status) }}
                </span>
            </div>
        </div>

        <div class="admin-booking-details-grid">
            <section class="admin-booking-detail-card admin-section-card" aria-labelledby="booking-information-heading">
                <div class="admin-booking-detail-heading">
                    <span class="admin-booking-detail-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-bookings-eyebrow">Request</p>
                        <h2 id="booking-information-heading">Booking information</h2>
                    </div>
                </div>
                <dl class="admin-booking-detail-list">
                    <div><dt>Reference</dt><dd>{{ $booking->booking_reference }}</dd></div>
                    <div><dt>Created</dt><dd>{{ $booking->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                    <div><dt>Last updated</dt><dd>{{ $booking->updated_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                    @if ($booking->customer_notes)
                        <div class="admin-booking-detail-full"><dt>Customer notes</dt><dd>{{ $booking->customer_notes }}</dd></div>
                    @endif
                    @if ($booking->admin_remarks)
                        <div class="admin-booking-detail-full"><dt>Admin notes</dt><dd>{{ $booking->admin_remarks }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="admin-booking-detail-card admin-section-card" aria-labelledby="customer-information-heading">
                <div class="admin-booking-detail-heading">
                    <span class="admin-booking-detail-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-bookings-eyebrow">Contact</p>
                        <h2 id="customer-information-heading">Customer information</h2>
                    </div>
                </div>
                <dl class="admin-booking-detail-list">
                    <div><dt>Full name</dt><dd>{{ $booking->customer?->full_name ?? 'Customer record unavailable' }}</dd></div>
                    <div><dt>Phone</dt><dd>{{ $booking->customer?->mobile_number ?? 'Not provided' }}</dd></div>
                    <div class="admin-booking-detail-full"><dt>Email</dt><dd>{{ $booking->customer?->email ?? 'Not provided' }}</dd></div>
                </dl>
            </section>

            <section class="admin-booking-detail-card admin-section-card" aria-labelledby="vehicle-information-heading">
                <div class="admin-booking-detail-heading">
                    <span class="admin-booking-detail-icon"><i class="bi bi-car-front" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-bookings-eyebrow">Fleet</p>
                        <h2 id="vehicle-information-heading">Vehicle information</h2>
                    </div>
                </div>
                <dl class="admin-booking-detail-list">
                    <div><dt>Vehicle</dt><dd>{{ $booking->vehicle?->name ?? 'Vehicle record unavailable' }}</dd></div>
                    <div><dt>Category</dt><dd>{{ $booking->vehicle?->category ? ucfirst($booking->vehicle->category) : 'Not recorded' }}</dd></div>
                </dl>
            </section>

            <section class="admin-booking-detail-card admin-section-card" aria-labelledby="rental-information-heading">
                <div class="admin-booking-detail-heading">
                    <span class="admin-booking-detail-icon"><i class="bi bi-calendar-range" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-bookings-eyebrow">Schedule and pricing</p>
                        <h2 id="rental-information-heading">Rental information</h2>
                    </div>
                </div>
                <dl class="admin-booking-detail-list">
                    <div><dt>Pickup</dt><dd>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                    <div><dt>Pickup location</dt><dd>{{ $booking->pickup_location }}</dd></div>
                    <div><dt>Return</dt><dd>{{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</dd></div>
                    <div><dt>Return location</dt><dd>{{ $booking->return_location }}</dd></div>
                    <div><dt>Duration</dt><dd>{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</dd></div>
                    <div><dt>Daily rate snapshot</dt><dd>₱{{ number_format((float) $booking->daily_rate_snapshot, 2) }}</dd></div>
                    <div class="admin-booking-detail-total"><dt>Subtotal / total</dt><dd>₱{{ number_format((float) $booking->estimated_total, 2) }}</dd></div>
                </dl>
            </section>

            <section class="admin-booking-detail-card admin-booking-detail-history admin-section-card" aria-labelledby="booking-history-heading">
                <div class="admin-booking-detail-heading">
                    <span class="admin-booking-detail-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-bookings-eyebrow">Audit trail</p>
                        <h2 id="booking-history-heading">Booking history</h2>
                    </div>
                </div>

                @forelse ($booking->auditLogs as $auditLog)
                    @php
                        $change = $auditLog->changes ?? [];
                    @endphp
                    <article class="admin-booking-history-item">
                        <span class="admin-booking-history-marker" aria-hidden="true"></span>
                        <div class="admin-booking-history-content">
                            <div class="admin-booking-history-title">
                                <strong>{{ $actionLabels[$auditLog->action] ?? ucfirst(str_replace('_', ' ', $auditLog->action)) }}</strong>
                                <time datetime="{{ $auditLog->created_at?->toIso8601String() }}">{{ $auditLog->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</time>
                            </div>
                            @if (isset($change['from'], $change['to']))
                                <p>{{ ucfirst($change['from']) }} <i class="bi bi-arrow-right" aria-hidden="true"></i> {{ ucfirst($change['to']) }}</p>
                            @endif
                            <small>By {{ $auditLog->user?->name ?? ($auditLog->action === 'booking_submitted' ? 'Customer / guest' : 'System') }}</small>
                            @if (! empty($change['reason']))
                                <p class="admin-booking-history-reason">Reason: {{ $change['reason'] }}</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="admin-booking-history-empty">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <span>No booking history has been recorded.</span>
                    </div>
                @endforelse
            </section>

            <section class="admin-booking-detail-card admin-booking-actions-card admin-section-card" aria-labelledby="reservation-actions-heading">
                <div class="admin-booking-detail-heading">
                    <span class="admin-booking-detail-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
                    <div>
                        <p class="admin-bookings-eyebrow">Authorized actions</p>
                        <h2 id="reservation-actions-heading">Manage reservation</h2>
                    </div>
                </div>

                @if ($allowedTransitions)
                    <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="admin-booking-status-form">
                        @csrf
                        @method('PATCH')
                        <label for="admin-remarks">Optional action note or reason</label>
                        <textarea id="admin-remarks" name="admin_remarks" maxlength="1000" rows="3" placeholder="Add context for this status change">{{ old('admin_remarks') }}</textarea>
                        <div class="admin-booking-action-buttons">
                            @foreach ($allowedTransitions as $transition)
                                @php [$label, $icon, $class] = $transitionLabels[$transition]; @endphp
                                <button
                                    class="admin-booking-action {{ $class }}"
                                    type="submit"
                                    name="status"
                                    value="{{ $transition }}"
                                    @if ($transition === \App\Models\Booking::STATUS_CANCELLED)
                                        onclick="return confirm('Cancel this reservation? The booking will be retained in its history.')"
                                    @elseif ($transition === \App\Models\Booking::STATUS_REJECTED)
                                        onclick="return confirm('Reject this pending reservation?')"
                                    @endif
                                >
                                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                                    <span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </form>
                @else
                    <div class="admin-booking-terminal-state">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>This reservation is in a terminal state and has no further available actions.</span>
                    </div>
                @endif
            </section>
        </div>
    </main>
@endsection
