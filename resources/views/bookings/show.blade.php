@extends('layouts.app')

@section('title', $booking->booking_reference . ' | Booking details')
@section('meta-description', 'Review booking details, status, and saved trip information.')

@section('content')
    @if (! empty($customerView))
        @include('partials.public-navigation')

        @php
            $statusMeta = match ($booking->status) {
                'pending' => ['Awaiting review', 'hourglass-split', 'pending'],
                'approved' => ['Confirmed', 'check-circle', 'approved'],
                'ongoing' => ['In progress', 'key', 'ongoing'],
                'completed' => ['Completed', 'check2-circle', 'completed'],
                'cancelled' => ['Cancelled', 'x-circle', 'cancelled'],
                'rejected' => ['Not approved', 'slash-circle', 'rejected'],
                default => [\Illuminate\Support\Str::headline($booking->status), 'info-circle', 'completed'],
            };
            $progressStage = match ($booking->status) {
                \App\Models\Booking::STATUS_PENDING => 1,
                \App\Models\Booking::STATUS_APPROVED => 2,
                \App\Models\Booking::STATUS_ONGOING => 3,
                \App\Models\Booking::STATUS_COMPLETED => 4,
                default => 0,
            };
            $isExceptionalStatus = in_array($booking->status, [
                \App\Models\Booking::STATUS_CANCELLED,
                \App\Models\Booking::STATUS_REJECTED,
            ], true);
        @endphp

        <main class="customer-booking-detail-page" id="main-content">
            <header class="customer-booking-detail-header">
                <div>
                    <a class="customer-booking-detail-back" href="{{ route('account.bookings') }}">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <span>Back to my bookings</span>
                    </a>
                    <p class="customer-booking-detail-eyebrow">Booking request</p>
                    <div class="customer-booking-detail-title">
                        <h1>Booking request {{ $booking->booking_reference }}</h1>
                        @include('partials.customer-booking-status', ['statusMeta' => $statusMeta])
                    </div>
                    <p class="customer-booking-detail-subtitle">Your reservation details and trip information.</p>
                </div>
            </header>

            <div class="customer-booking-detail-content">
                @if (session('status'))
                    <p class="customer-status" role="status" aria-live="polite">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <p class="customer-booking-alert" role="alert">{{ $errors->first() }}</p>
                @endif

                <div class="customer-booking-layout">
                    <p class="customer-booking-status-message" aria-live="polite">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span><strong>{{ $statusMeta[0] }}:</strong> {{ $statusDescription }}</span>
                    </p>

                    <section class="customer-booking-progress {{ $isExceptionalStatus ? 'is-exception' : '' }}" aria-label="Booking progress">
                        @if ($isExceptionalStatus)
                            <div class="customer-booking-progress-exception customer-booking-status--{{ $statusMeta[2] }}">
                                <i class="bi bi-{{ $statusMeta[1] }}" aria-hidden="true"></i>
                                <span><strong>{{ $statusMeta[0] }}</strong> · {{ $statusDescription }}</span>
                            </div>
                        @else
                            <ol class="customer-booking-progress-steps" style="--booking-progress: {{ max(0, ($progressStage - 1) / 4 * 100) }}%">
                                @foreach (['Requested', 'Confirmed', 'In progress', 'Completed'] as $stageIndex => $stageLabel)
                                    <li @class([
                                        'is-complete' => $stageIndex + 1 < $progressStage,
                                        'is-current' => $stageIndex + 1 === $progressStage,
                                    ]) @if ($stageIndex + 1 === $progressStage) aria-current="step" @endif>
                                        <span class="customer-booking-progress-marker">
                                            @if ($stageIndex + 1 < $progressStage)
                                                <i class="bi bi-check2" aria-hidden="true"></i>
                                            @else
                                                {{ $stageIndex + 1 }}
                                            @endif
                                        </span>
                                        <span>{{ $stageLabel }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>

                    <div class="customer-booking-detail-grid">
                        <div class="customer-booking-detail-column">
                            <section class="customer-card customer-booking-trip-card" aria-labelledby="booking-trip-title">
                                <div class="customer-booking-detail-section-heading">
                                    <p class="customer-eyebrow"><span aria-hidden="true"></span> Trip summary</p>
                                    <h2 id="booking-trip-title">Pickup and return</h2>
                                </div>
                                <div class="customer-booking-trip-timeline">
                                    <div class="customer-booking-trip-stop">
                                        <span class="customer-booking-trip-marker" aria-hidden="true"></span>
                                        <div>
                                            <span class="customer-booking-trip-label">Pickup</span>
                                            <strong>{{ $booking->pickup_datetime->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</strong>
                                            <span>{{ $booking->pickup_location }}</span>
                                        </div>
                                    </div>
                                    <div class="customer-booking-trip-stop customer-booking-trip-stop--return">
                                        <span class="customer-booking-trip-marker" aria-hidden="true"></span>
                                        <div>
                                            <span class="customer-booking-trip-label">Return</span>
                                            <strong>{{ $booking->return_datetime->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</strong>
                                            <span>{{ $booking->return_location }}</span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="customer-card customer-booking-notes-card" aria-labelledby="booking-notes-title">
                                <div class="customer-booking-detail-section-heading">
                                    <p class="customer-eyebrow"><span aria-hidden="true"></span> A note for the team</p>
                                    <h2 id="booking-notes-title">Customer notes</h2>
                                </div>
                                <div class="customer-booking-notes">
                                    <p>{{ $booking->customer_notes ?: 'No notes were added to this request.' }}</p>
                                </div>
                            </section>
                        </div>

                        <div class="customer-booking-detail-column">
                            <section class="customer-card customer-booking-vehicle-card" aria-labelledby="booking-vehicle-title">
                                <div class="customer-booking-detail-section-heading">
                                    <p class="customer-eyebrow"><span aria-hidden="true"></span> Your vehicle</p>
                                    <h2 id="booking-vehicle-title">{{ $booking->vehicle->name }}</h2>
                                    <span class="customer-booking-vehicle-category">{{ ucfirst($booking->vehicle->category) }}</span>
                                </div>
                                <div class="customer-booking-vehicle-image">
                                    <i class="bi bi-car-front" aria-hidden="true"></i>
                                    @if ($booking->vehicle->image_path)
                                        <img src="{{ asset($booking->vehicle->image_path) }}" alt="" data-booking-vehicle-image>
                                    @endif
                                </div>
                            </section>

                            <section class="customer-card customer-booking-price-card" aria-labelledby="booking-price-title">
                                <div class="customer-booking-detail-section-heading">
                                    <p class="customer-eyebrow"><span aria-hidden="true"></span> Price summary</p>
                                    <h2 id="booking-price-title">Estimated total</h2>
                                </div>
                                <p class="customer-booking-price-total">₱{{ number_format((float) $booking->estimated_total, 2) }}</p>
                                <dl class="customer-booking-price-facts">
                                    <div><dt>Rental duration</dt><dd>{{ $booking->rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $booking->rental_duration_days) }}</dd></div>
                                    <div><dt>Daily rate</dt><dd>₱{{ number_format((float) $booking->daily_rate_snapshot, 2) }}</dd></div>
                                </dl>
                                <p class="customer-estimate-note">Estimated amount only. No payment has been taken.</p>
                            </section>
                        </div>
                    </div>

                    <section class="customer-card customer-timeline-card customer-booking-activity-card" aria-labelledby="booking-timeline-title">
                    <p class="customer-eyebrow"><span aria-hidden="true"></span> Updates</p>
                    <h2 id="booking-timeline-title">Booking activity</h2>
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
                        @php
                            $contactPhone = config('fleetguard.phone');
                            $contactPhone = is_string($contactPhone) && trim($contactPhone) !== '' ? trim($contactPhone) : null;
                            $contactPhoneLink = $contactPhone ? preg_replace('/[^0-9+().,;*#]/', '', $contactPhone) : null;
                            $contactEmail = config('fleetguard.email');
                            $contactEmail = is_string($contactEmail) && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? $contactEmail : null;
                        @endphp
                        <section class="customer-booking-guidance" aria-labelledby="approved-booking-guidance">
                            <i class="bi bi-telephone" aria-hidden="true"></i>
                            <div>
                                <h2 id="approved-booking-guidance">Need assistance?</h2>
                                <p>Approved bookings cannot be cancelled online. Contact FleetGuard for help with this reservation.</p>
                                <dl class="customer-guidance-contact">
                                    <div>
                                        <dt>Phone</dt>
                                        <dd>
                                            @if ($contactPhone)
                                                <a href="tel:{{ $contactPhoneLink }}">{{ $contactPhone }}</a>
                                            @else
                                                <span>To be added</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Email</dt>
                                        <dd>
                                            @if ($contactEmail)
                                                <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                                            @else
                                                <span>To be added</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </section>
                    @endif
                </div>

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
                    <textarea id="customer-cancel-reason" name="reason" maxlength="250" rows="3" placeholder="Share a short reason, if you’d like." aria-describedby="cancel-reason-counter" data-cancel-reason></textarea>
                    <small class="customer-reason-counter" id="cancel-reason-counter" aria-live="polite" aria-atomic="true"><span data-cancel-reason-count>0</span> / 250</small>
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
                <h1 class="mt-2 text-3xl font-semibold text-slate-900">Booking request {{ $booking->booking_reference }}</h1>

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
