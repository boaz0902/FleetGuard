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
            'customer_note_added' => 'Internal note added',
            'customer_blocked' => 'Customer blocked',
            'customer_unblocked' => 'Customer unblocked',
            'booking_submitted' => 'Reservation submitted',
            'booking_approved' => 'Reservation approved',
            'booking_rejected' => 'Reservation rejected',
            'booking_cancelled' => 'Reservation cancelled',
            'booking_marked_ongoing' => 'Rental marked ongoing',
            'booking_marked_completed' => 'Rental marked completed',
            'booking_status_changed' => 'Reservation status changed',
        ];
    @endphp

    <main class="admin-customer-details" id="main-content">
        @if (session('status'))
            <div class="admin-customers-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="admin-customers-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
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
                    <div class="admin-customer-name-line">
                        <h2>{{ $customer->full_name }}</h2>
                        @if ($customer->user?->hasVerifiedEmail())
                            <span class="admin-customer-verified-badge" aria-label="Customer email verified">
                                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                Verified
                            </span>
                        @endif
                    </div>
                    <span class="admin-customer-status admin-customer-status--{{ $customer->status }}">
                        <i class="bi bi-{{ $customer->status === \App\Models\Customer::STATUS_BLOCKED ? 'slash-circle' : 'check-circle' }}" aria-hidden="true"></i>
                        {{ ucfirst($customer->status) }}
                    </span>
                    <span>Record created {{ $customer->created_at?->timezone('Asia/Manila')->format('M d, Y') }}</span>
                </div>
                <div class="admin-customer-banner-actions">
                    @if ($canManageStatus && $customer->status === \App\Models\Customer::STATUS_ACTIVE)
                        <button class="admin-customer-block-button" type="button" data-customer-dialog-open="customer-block-dialog">
                            <i class="bi bi-slash-circle" aria-hidden="true"></i><span>Block customer</span>
                        </button>
                    @elseif ($canManageStatus)
                        <button class="admin-customer-unblock-button" type="button" data-customer-dialog-open="customer-unblock-dialog">
                            <i class="bi bi-unlock" aria-hidden="true"></i><span>Unblock customer</span>
                        </button>
                    @endif
                </div>
            </section>
        </div>

        @if ($details['blocked_event'])
            <section class="admin-customer-blocked-banner" aria-label="Customer blocking details">
                <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                <div>
                <strong>This customer is blocked from new bookings.</strong>
                <span>Reason: {{ $details['blocked_event']->changes['reason'] ?? 'No reason recorded' }}</span>
                <small>By {{ $details['blocked_event']->user?->name ?? 'Former administrator' }} · {{ $details['blocked_event']->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</small>
                </div>
            </section>
        @endif

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
                        @if (! $canViewContact)
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
                <div class="admin-customer-notes-layout">
                    <section class="admin-customer-notes-section" aria-labelledby="customer-notes-heading">
                        <div class="admin-customer-bookings-heading">
                            <div><p class="admin-customers-eyebrow">Internal record</p><h3 id="customer-notes-heading">Customer notes</h3></div>
                            <span>Newest first · available to authorized customer viewers</span>
                        </div>
                        @if ($details['notes']->isEmpty())
                            <div class="admin-customer-tab-empty admin-customer-notes-empty">
                                <span class="admin-customer-notes-empty-icon"><i class="bi bi-sticky" aria-hidden="true"></i></span>
                                <strong>No internal notes yet</strong>
                                <span>Admins can record useful customer-service context. Notes are private and cannot be edited or deleted.</span>
                            </div>
                        @else
                            <ol class="admin-customer-notes-timeline">
                                @foreach ($details['notes'] as $note)
                                    @php
                                        $authorParts = preg_split('/\s+/', trim($note->author?->name ?? 'Former user')) ?: [];
                                        $authorInitials = collect($authorParts)->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                                    @endphp
                                    <li class="admin-customer-note">
                                        <span class="admin-customer-note-avatar" aria-hidden="true">{{ $authorInitials ?: '?' }}</span>
                                        <article>
                                            <header>
                                                <strong>{{ $note->author?->name ?? 'Former user' }}</strong>
                                                <time datetime="{{ $note->created_at?->toIso8601String() }}" title="{{ $note->created_at?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}">{{ $note->created_at?->diffForHumans() }}</time>
                                            </header>
                                            <p>{{ $note->body }}</p>
                                        </article>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>

                    @if ($canAddNotes)
                        <section class="admin-customer-note-form-card" aria-labelledby="add-customer-note-heading">
                            <div class="admin-customer-detail-heading">
                                <span class="admin-customer-detail-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
                                <div><p class="admin-customers-eyebrow">Admin only</p><h3 id="add-customer-note-heading">Add an internal note</h3></div>
                            </div>
                            <p>Never visible in customer-facing pages. Notes are append-only.</p>
                            <form method="POST" action="{{ route('admin.customers.notes.store', $customer) }}" data-admin-disable-submit>
                                @csrf
                                <label for="customer-note-body">Note</label>
                                <textarea id="customer-note-body" name="body" rows="5" maxlength="5000" required data-customer-note-input>{{ old('body') }}</textarea>
                                @error('body')<small class="admin-customer-field-error">{{ $message }}</small>@enderror
                                <div class="admin-customer-note-form-footer">
                                    <small><span data-customer-note-count>0</span> / 5000 characters</small>
                                    <button class="admin-customer-primary-link" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add note</span></button>
                                </div>
                            </form>
                        </section>
                    @endif
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

        @if ($canManageStatus)
            <dialog class="admin-customer-status-dialog" id="customer-block-dialog" aria-labelledby="customer-block-title" @if ($errors->has('reason')) data-dialog-open-on-load @endif>
                <div class="admin-customer-status-dialog-heading">
                    <span class="admin-customer-status-dialog-icon admin-customer-status-dialog-icon--danger"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                    <button class="admin-customer-dialog-close" type="button" data-customer-dialog-close aria-label="Close block confirmation"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <h2 id="customer-block-title">Block this customer?</h2>
                <p>Blocking only prevents new submissions that match this customer's registered email. Existing pending, approved, ongoing, and completed bookings are untouched; check-out and check-in remain available.</p>
                <form method="POST" action="{{ route('admin.customers.block', $customer) }}" data-admin-disable-submit>
                    @csrf
                    <label for="customer-block-reason">Reason for blocking</label>
                    <textarea id="customer-block-reason" name="reason" rows="4" maxlength="1000" required>{{ old('reason') }}</textarea>
                    @error('reason')<small class="admin-customer-field-error">{{ $message }}</small>@enderror
                    <div class="admin-customer-status-dialog-actions">
                        <button class="admin-customer-secondary-link" type="button" data-customer-dialog-close>Cancel</button>
                        <button class="admin-customer-danger-button" type="submit">Block customer</button>
                    </div>
                </form>
            </dialog>

            <dialog class="admin-customer-status-dialog" id="customer-unblock-dialog" aria-labelledby="customer-unblock-title">
                <div class="admin-customer-status-dialog-heading">
                    <span class="admin-customer-status-dialog-icon"><i class="bi bi-unlock" aria-hidden="true"></i></span>
                    <button class="admin-customer-dialog-close" type="button" data-customer-dialog-close aria-label="Close unblock confirmation"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <h2 id="customer-unblock-title">Unblock this customer?</h2>
                <p>New booking submissions matching this customer's registered email will be allowed again. Existing bookings remain unchanged.</p>
                <form method="POST" action="{{ route('admin.customers.unblock', $customer) }}" data-admin-disable-submit>
                    @csrf
                    <div class="admin-customer-status-dialog-actions">
                        <button class="admin-customer-secondary-link" type="button" data-customer-dialog-close>Cancel</button>
                        <button class="admin-customer-primary-link" type="submit"><i class="bi bi-unlock" aria-hidden="true"></i><span>Unblock customer</span></button>
                    </div>
                </form>
            </dialog>
        @endif
    </main>
@endsection
