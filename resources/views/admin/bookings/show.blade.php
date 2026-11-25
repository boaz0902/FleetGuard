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
            'rental_checked_out' => 'Rental checked out',
            'rental_checked_in' => 'Rental checked in',
            'booking_status_changed' => 'Status changed',
        ];
        $transitionLabels = [
            \App\Models\Booking::STATUS_APPROVED => ['Approve reservation', 'check2-circle', 'admin-booking-action--approve'],
            \App\Models\Booking::STATUS_REJECTED => ['Reject request', 'x-circle', 'admin-booking-action--reject'],
            \App\Models\Booking::STATUS_CANCELLED => ['Cancel reservation', 'slash-circle', 'admin-booking-action--cancel'],
        ];
        $identityDocuments = [
            ['key' => 'license-front', 'label' => 'Driver’s license · front', 'path' => $booking->license_front_path],
            ['key' => 'license-back', 'label' => 'Driver’s license · back', 'path' => $booking->license_back_path],
            ['key' => 'license-selfie', 'label' => 'Customer holding license', 'path' => $booking->license_selfie_path],
        ];
    @endphp

    <main class="admin-booking-details {{ $allowedTransitions || $booking->status === \App\Models\Booking::STATUS_ONGOING ? 'has-sticky-actions' : '' }}" id="main-content">
        <div class="admin-booking-details-grid">
            <nav class="admin-booking-details-header" aria-label="Reservation breadcrumbs">
                <a class="admin-bookings-back" href="{{ request()->filled('calendar_start_date') ? route('admin.calendar.index', ['start_date' => request()->query('calendar_start_date')]) : route('admin.bookings.index', request()->query()) }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>{{ request()->filled('calendar_start_date') ? 'Back to availability calendar' : 'Back to reservations' }}</span>
                </a>
                <ol class="admin-booking-breadcrumbs">
                    <li>FleetGuard</li>
                    <li aria-hidden="true">/</li>
                    <li>Operations</li>
                    <li aria-hidden="true">/</li>
                    <li>Reservations</li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">{{ $booking->booking_reference }}</li>
                </ol>
            </nav>

            <section class="admin-booking-reference-card admin-section-card" aria-label="Reservation summary">
                <div class="admin-booking-reference-heading">
                    <div>
                        <p class="admin-bookings-eyebrow">Reservation reference</p>
                        <h2>{{ $booking->booking_reference }}</h2>
                    </div>
                    <span class="admin-status-badge admin-status--{{ $booking->status }}">
                        <i class="bi bi-{{ $statusIcons[$booking->status] ?? 'question-circle' }}" aria-hidden="true"></i>
                        {{ ucfirst($booking->status) }}
                    </span>
                </div>
                <div class="admin-booking-hero-meta">
                    <span>
                        <small>Requested</small>
                        <strong>{{ $booking->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</strong>
                    </span>
                    <span>
                        <small>Rental duration</small>
                        <strong>{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</strong>
                    </span>
                    <span>
                        <small>Estimated total</small>
                        <strong>₱{{ number_format((float) $booking->estimated_total, 2) }}</strong>
                    </span>
                </div>
            </section>

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

            @unless ($booking->customer?->user?->hasVerifiedEmail())
                <aside class="admin-customer-verification-alert" role="note" aria-labelledby="customer-verification-heading">
                    <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                    <div class="admin-customer-verification-content">
                        <strong id="customer-verification-heading">
                            {{ $booking->customer?->user ? 'Customer email is not verified' : 'No verified customer account is linked' }}
                        </strong>
                        <p>
                            {{ $booking->customer?->user
                                ? 'Confirm the booking with the customer before approval. If their details cannot be verified, reject the request.'
                                : 'This reservation is not linked to an email-verified account. Contact the customer to confirm the request before approval, or reject it if you cannot verify their details.' }}
                        </p>
                        @if ($booking->customer?->email || $booking->customer?->mobile_number)
                            <div class="admin-customer-verification-contacts">
                                @if ($booking->customer?->email)
                                    <a href="mailto:{{ $booking->customer->email }}">
                                        <i class="bi bi-envelope" aria-hidden="true"></i>
                                        <span>Email customer</span>
                                    </a>
                                @endif
                                @if ($booking->customer?->mobile_number)
                                    <a href="sms:{{ preg_replace('/[^0-9+]/', '', $booking->customer->mobile_number) }}">
                                        <i class="bi bi-chat-dots" aria-hidden="true"></i>
                                        <span>Text customer</span>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </aside>
            @endunless

            <div class="admin-booking-details-column admin-booking-details-column--left">
                <section class="admin-booking-detail-card admin-booking-trip-card admin-section-card" aria-labelledby="booking-trip-heading">
                    <div class="admin-booking-detail-heading">
                        <span class="admin-booking-detail-icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></span>
                        <div class="admin-booking-trip-heading-copy">
                            <p class="admin-bookings-eyebrow">Trip summary</p>
                            <h2 id="booking-trip-heading">Pickup and return</h2>
                        </div>
                        <div class="admin-booking-trip-heading-meta">
                            <span class="admin-booking-duration-chip">{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</span>
                            <small>Last updated {{ $booking->updated_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</small>
                        </div>
                    </div>
                    <div class="admin-booking-trip-timeline">
                        <div class="admin-booking-trip-stop">
                            <span class="admin-booking-trip-marker" aria-hidden="true"></span>
                            <div>
                                <span class="admin-booking-trip-label">Pickup</span>
                                <strong>{{ $booking->pickup_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</strong>
                                <span>{{ $booking->pickup_location }}</span>
                            </div>
                        </div>
                        <div class="admin-booking-trip-stop admin-booking-trip-stop--return">
                            <span class="admin-booking-trip-marker" aria-hidden="true"></span>
                            <div>
                                <span class="admin-booking-trip-label">Return</span>
                                <strong>{{ $booking->return_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</strong>
                                <span>{{ $booking->return_location }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-booking-detail-card admin-booking-notes-card{{ $booking->customer_notes ? '' : ' admin-booking-notes-card--empty' }} admin-section-card" aria-labelledby="booking-notes-heading">
                    <div class="admin-booking-detail-heading">
                        <span class="admin-booking-detail-icon"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-bookings-eyebrow">Customer message</p>
                            <h2 id="booking-notes-heading">Customer notes</h2>
                        </div>
                    </div>
                    <p class="admin-booking-notes-copy{{ $booking->customer_notes ? '' : ' admin-booking-notes-copy--empty' }}">{{ $booking->customer_notes ?: 'No customer notes were provided.' }}</p>
                    @if ($booking->admin_remarks)
                        <div class="admin-booking-internal-note">
                            <strong>Latest admin note</strong>
                            <p>{{ $booking->admin_remarks }}</p>
                        </div>
                    @endif
                </section>

                <section class="admin-booking-detail-card admin-booking-identity-card admin-section-card" aria-labelledby="booking-identity-heading">
                    <div class="admin-booking-detail-heading">
                        <span class="admin-booking-detail-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-bookings-eyebrow">Private customer uploads</p>
                            <h2 id="booking-identity-heading">Identity documents</h2>
                        </div>
                    </div>
                    <p class="admin-booking-identity-note">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        These images are private. View them only as needed for booking review.
                    </p>
                    <div class="admin-booking-documents-grid">
                        @foreach ($identityDocuments as $document)
                            <article class="admin-booking-document">
                                <h3>{{ $document['label'] }}</h3>
                                @if ($document['path'])
                                    <a class="admin-booking-document-preview" href="{{ route('admin.bookings.documents.show', ['booking' => $booking, 'document' => $document['key']]) }}" target="_blank" rel="noopener noreferrer">
                                        <img
                                            src="{{ route('admin.bookings.documents.show', ['booking' => $booking, 'document' => $document['key']]) }}"
                                            alt="{{ $document['label'] }}"
                                            loading="lazy"
                                        >
                                        <span><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i> Open full image</span>
                                    </a>
                                @else
                                    <div class="admin-booking-document-empty">
                                        <i class="bi bi-image" aria-hidden="true"></i>
                                        <span>Not provided</span>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
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
                                </div>
                                @if (isset($change['from'], $change['to']))
                                    <p class="admin-booking-history-transition">
                                        <span class="admin-status-badge admin-status--{{ $change['from'] }}">{{ ucfirst($change['from']) }}</span>
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                        <span class="admin-status-badge admin-status--{{ $change['to'] }}">{{ ucfirst($change['to']) }}</span>
                                    </p>
                                @endif
                                <div class="admin-booking-history-meta">
                                    <small>By {{ $auditLog->user?->name ?? ($auditLog->action === 'booking_submitted' ? 'Customer / guest' : 'System') }}</small>
                                    <time datetime="{{ $auditLog->created_at?->toIso8601String() }}">{{ $auditLog->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</time>
                                </div>
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
            </div>

            <div class="admin-booking-details-column admin-booking-details-column--right">
                <section class="admin-booking-detail-card admin-booking-actions-card admin-section-card {{ $allowedTransitions || $booking->status === \App\Models\Booking::STATUS_ONGOING ? 'has-actions' : '' }}" aria-labelledby="reservation-actions-heading">
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
                            <details class="admin-booking-note-disclosure" open>
                                <summary>Add an optional action note</summary>
                                <label for="admin-remarks">Optional action note or reason</label>
                                <textarea id="admin-remarks" name="admin_remarks" maxlength="1000" rows="2" placeholder="Add context for this status change">{{ old('admin_remarks') }}</textarea>
                            </details>
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
                    @elseif ($booking->status === \App\Models\Booking::STATUS_ONGOING)
                        <div class="admin-booking-terminal-state">
                            <i class="bi bi-key" aria-hidden="true"></i>
                            <span>Check-in actions are available from the Rental details page.</span>
                        </div>
                    @else
                        <div class="admin-booking-terminal-state">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            <span>This reservation is in a terminal state and has no further available actions.</span>
                        </div>
                    @endif
                    @if (in_array($booking->status, [\App\Models\Booking::STATUS_APPROVED, \App\Models\Booking::STATUS_ONGOING, \App\Models\Booking::STATUS_COMPLETED], true))
                        <a class="admin-bookings-back" href="{{ route('admin.rentals.show', $booking) }}">
                            <i class="bi bi-key" aria-hidden="true"></i>
                            <span>View rental details</span>
                        </a>
                    @endif
                </section>

                <section class="admin-booking-detail-card admin-booking-price-card admin-section-card" aria-labelledby="booking-price-heading">
                    <div class="admin-booking-detail-heading">
                        <span class="admin-booking-detail-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-bookings-eyebrow">Estimate</p>
                            <h2 id="booking-price-heading">Price summary</h2>
                        </div>
                    </div>
                    <p class="admin-booking-price-total">₱{{ number_format((float) $booking->estimated_total, 2) }}</p>
                    <div class="admin-booking-price-meta">
                        <span>{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</span>
                        <span>₱{{ number_format((float) $booking->daily_rate_snapshot, 2) }} per day</span>
                    </div>
                    <p class="admin-booking-price-note">Estimated total at the time of request.</p>
                </section>

                <section class="admin-booking-detail-card admin-booking-vehicle-card admin-section-card" aria-labelledby="vehicle-information-heading">
                    <div class="admin-booking-detail-heading">
                        <span class="admin-booking-detail-icon"><i class="bi bi-car-front" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-bookings-eyebrow">Fleet</p>
                            <h2 id="vehicle-information-heading">Vehicle</h2>
                        </div>
                    </div>
                    <div class="admin-booking-vehicle-summary">
                        <span class="admin-booking-vehicle-photo">
                            @if ($booking->vehicle?->image_path)
                                <img src="{{ asset($booking->vehicle->image_path) }}" alt="" loading="lazy">
                            @else
                                <i class="bi bi-car-front" aria-hidden="true"></i>
                            @endif
                        </span>
                        <div>
                            <strong>{{ $booking->vehicle?->name ?? 'Vehicle record unavailable' }}</strong>
                            <span class="admin-booking-vehicle-category">{{ $booking->vehicle?->category ? ucfirst($booking->vehicle->category) : 'Category not recorded' }}</span>
                        </div>
                    </div>
                </section>

                <section class="admin-booking-detail-card admin-booking-customer-card admin-section-card" aria-labelledby="customer-information-heading">
                    <div class="admin-booking-detail-heading">
                        <span class="admin-booking-detail-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                        <div>
                            <p class="admin-bookings-eyebrow">Contact</p>
                            <h2 id="customer-information-heading">Customer</h2>
                        </div>
                    </div>
                    <dl class="admin-booking-detail-list">
                        <div>
                            <dt>Full name</dt>
                            <dd class="admin-booking-customer-name">
                                <span class="admin-booking-customer-name-text">{{ $booking->customer?->full_name ?? 'Customer record unavailable' }}</span>
                                @if ($booking->customer?->user?->hasVerifiedEmail())
                                    <span class="admin-customer-verified-badge" aria-label="Customer email verified">
                                        <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                        Verified
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div><dt>Phone</dt><dd>{{ $booking->customer?->mobile_number ?? 'Not provided' }}</dd></div>
                        <div class="admin-booking-detail-full"><dt>Email</dt><dd>{{ $booking->customer?->email ?? 'Not provided' }}</dd></div>
                    </dl>
                    @if ($booking->customer?->email || $booking->customer?->mobile_number)
                        <div class="admin-booking-customer-actions" aria-label="Contact customer">
                            @if ($booking->customer?->email)
                                <a href="mailto:{{ $booking->customer->email }}" aria-label="Email customer" title="Email customer">
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    <span>Email</span>
                                </a>
                            @endif
                            @if ($booking->customer?->mobile_number)
                                <a href="sms:{{ preg_replace('/[^0-9+]/', '', $booking->customer->mobile_number) }}" aria-label="Text customer" title="Text customer">
                                    <i class="bi bi-chat-dots" aria-hidden="true"></i>
                                    <span>Text</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </main>
    <script>
        (() => {
            const noteDisclosure = document.querySelector('.admin-booking-note-disclosure');
            if (!noteDisclosure) {
                return;
            }

            const mobileLayout = window.matchMedia('(max-width: 767px)');
            const updateNoteDisclosure = () => {
                noteDisclosure.open = !mobileLayout.matches;
            };

            updateNoteDisclosure();
            mobileLayout.addEventListener('change', updateNoteDisclosure);
        })();
    </script>
@endsection
