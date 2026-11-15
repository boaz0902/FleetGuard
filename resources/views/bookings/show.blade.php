@extends('layouts.app')

@section('title', 'Booking Details')

@section('content')
    @if (! empty($customerView))
        @include('partials.public-navigation')

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

        <main class="customer-dashboard-page customer-bookings-page customer-booking-detail-page" id="main-content">
            <header class="customer-bookings-hero">
                <div class="customer-bookings-hero-inner">
                    <div>
                        <p class="customer-hero-eyebrow">My Bookings / Details</p>
                        <h1>{{ $booking->booking_reference }}</h1>
                        <p class="customer-hero-email">Reservation details and status updates.</p>
                    </div>
                    <span class="customer-booking-status customer-booking-status--{{ $statusMeta[2] }}">
                        <i class="bi bi-{{ $statusMeta[1] }}" aria-hidden="true"></i> {{ $statusMeta[0] }}
                    </span>
                </div>
            </header>

            <div class="customer-dashboard-content">
                @if (session('status'))
                    <p class="customer-status" role="status" aria-live="polite">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <p class="customer-booking-alert" role="alert">{{ $errors->first() }}</p>
                @endif

                <p class="customer-booking-status-message">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span><strong>{{ $statusMeta[0] }}:</strong> {{ $statusDescription }}</span>
                </p>

                <section class="customer-card customer-reservation-card" aria-labelledby="reservation-summary-title">
                    <div class="customer-reservation-heading">
                        <div class="customer-booking-image customer-detail-image">
                            <i class="bi bi-car-front" aria-hidden="true"></i>
                            @if ($booking->vehicle->image_path)
                                <img src="{{ asset($booking->vehicle->image_path) }}" alt="" data-booking-vehicle-image>
                            @endif
                        </div>
                        <div>
                            <p class="customer-eyebrow"><span aria-hidden="true"></span> {{ $booking->vehicle->category }}</p>
                            <h2 id="reservation-summary-title">{{ $booking->vehicle->name }}</h2>
                            <span>{{ $booking->booking_reference }}</span>
                        </div>
                    </div>

                    <div class="customer-reservation-route">
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

                    <dl class="customer-reservation-facts">
                        <div><dt>Rental duration</dt><dd>{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</dd></div>
                        <div><dt>Daily rate at booking</dt><dd>₱{{ number_format((float) $booking->daily_rate_snapshot, 2) }}</dd></div>
                        <div><dt>Estimated total</dt><dd>₱{{ number_format((float) $booking->estimated_total, 2) }}</dd></div>
                        <div><dt>Request submitted</dt><dd>{{ $booking->created_at->format('M j, Y · g:i A') }}</dd></div>
                    </dl>
                    <p class="customer-estimate-note">Estimated amount only. No payment has been taken.</p>

                    @if ($booking->customer_notes)
                        <div class="customer-booking-notes">
                            <h3>Your booking note</h3>
                            <p>{{ $booking->customer_notes }}</p>
                        </div>
                    @endif
                </section>

                <section class="customer-card customer-timeline-card" aria-labelledby="booking-timeline-title">
                    <p class="customer-eyebrow"><span aria-hidden="true"></span> Booking progress</p>
                    <h2 id="booking-timeline-title">Status timeline</h2>
                    <ol class="customer-status-timeline">
                        @foreach ($timeline as $event)
                            @php
                                $eventIcon = match ($event['label']) {
                                    'Request submitted' => 'send',
                                    'Approved' => 'check2',
                                    'Checked out' => 'key',
                                    'Checked in' => 'box-arrow-in-left',
                                    'Completed' => 'check2-circle',
                                    'Cancelled' => 'x-circle',
                                    'Not approved' => 'slash-circle',
                                    default => 'circle',
                                };
                            @endphp
                            <li class="customer-timeline-event {{ $event['complete'] ? 'is-complete' : '' }}">
                                <span class="customer-timeline-marker"><i class="bi bi-{{ $eventIcon }}" aria-hidden="true"></i></span>
                                <div>
                                    <h3>{{ $event['label'] }}</h3>
                                    <p>{{ $event['description'] }}</p>
                                    @if ($event['reason'])
                                        <p class="customer-cancellation-reason"><strong>Your note:</strong> {{ $event['reason'] }}</p>
                                    @endif
                                    @if ($event['at'])
                                        <time datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}</time>
                                    @else
                                        <span class="customer-timeline-pending">Waiting for this step</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if ($booking->status === \App\Models\Booking::STATUS_PENDING)
                    <section class="customer-cancel-panel" aria-labelledby="cancel-booking-heading">
                        <span class="customer-cancel-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                        <div>
                            <h2 id="cancel-booking-heading">Need to cancel this request?</h2>
                            <p>You can cancel while it is pending. Cancelling ends this request; it does not change your dates or estimated amount.</p>
                        </div>
                        <button class="customer-cancel-trigger" type="button" data-cancel-dialog-open>Cancel request</button>
                    </section>
                @elseif ($booking->status === \App\Models\Booking::STATUS_APPROVED)
                    <p class="customer-booking-guidance"><i class="bi bi-telephone" aria-hidden="true"></i> Approved bookings cannot be cancelled online. Contact FleetGuard for assistance with your reservation.</p>
                @endif

                <a class="customer-back-link" href="{{ route('account.bookings') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to My Bookings</a>
            </div>
        </main>

        @if ($booking->status === \App\Models\Booking::STATUS_PENDING)
            <dialog class="customer-cancel-dialog" aria-labelledby="cancel-dialog-title" data-cancel-dialog>
                <form method="POST" action="{{ route('account.bookings.cancel', $booking) }}" data-cancel-form>
                    @csrf
                    <span class="customer-cancel-dialog-icon"><i class="bi bi-calendar-x" aria-hidden="true"></i></span>
                    <button class="customer-dialog-close" type="button" aria-label="Close cancellation dialog" data-cancel-dialog-close>
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                    <p class="customer-eyebrow"><span aria-hidden="true"></span> Booking cancellation</p>
                    <h2 id="cancel-dialog-title">Cancel this request?</h2>
                    <p class="customer-cancel-dialog-copy">This will cancel {{ $booking->booking_reference }}. You can’t undo this action.</p>
                    <label for="customer-cancel-reason">Reason <span>(optional)</span></label>
                    <textarea id="customer-cancel-reason" name="reason" maxlength="250" rows="3" placeholder="Share a short reason, if you’d like." data-cancel-reason></textarea>
                    <small class="customer-reason-counter"><span data-cancel-reason-count>0</span> / 250</small>
                    <div class="customer-cancel-dialog-actions">
                        <button class="customer-cancel-secondary" type="button" data-cancel-dialog-close>Keep request</button>
                        <button class="customer-cancel-confirm" type="submit" data-cancel-submit>
                            <i class="bi bi-x-circle" aria-hidden="true"></i> Confirm cancellation
                        </button>
                    </div>
                </form>
            </dialog>
        @endif
    @else
        <div class="mx-auto max-w-4xl px-6 py-10">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <p class="text-sm uppercase tracking-[0.25em] text-cyan-500">Booking request</p>
                <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ $booking->booking_reference }}</h1>

                <dl class="mt-6 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm text-slate-500">Customer</dt>
                        <dd class="mt-1 text-lg font-medium text-slate-900">{{ $booking->customer->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Vehicle</dt>
                        <dd class="mt-1 text-lg font-medium text-slate-900">{{ $booking->vehicle->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Pickup</dt>
                        <dd class="mt-1 text-slate-700">{{ $booking->pickup_datetime->format('M d, Y H:i') }} · {{ $booking->pickup_location }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Return</dt>
                        <dd class="mt-1 text-slate-700">{{ $booking->return_datetime->format('M d, Y H:i') }} · {{ $booking->return_location }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Status</dt>
                        <dd class="mt-1 text-slate-700">{{ $booking->status }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Estimated total</dt>
                        <dd class="mt-1 text-slate-700">₱{{ number_format((float) $booking->estimated_total, 2) }}</dd>
                    </div>
                </dl>

                @if ($booking->customer_notes)
                    <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                        <p class="font-medium text-slate-900">Customer notes</p>
                        <p class="mt-2">{{ $booking->customer_notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
