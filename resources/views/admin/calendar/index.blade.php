@extends('layouts.admin')

@section('title', 'Fleet Availability | FleetGuard')
@section('admin-page-title', 'Fleet Availability')
@section('admin-page-icon', 'calendar-week')
@section('admin-breadcrumb', 'Operations')
@section('admin-page-description', 'Monitor vehicle reservations and maintenance schedules.')

@section('admin-content')
    @php
        $rangeStartTimestamp = $start->getTimestamp();
        $rangeEndTimestamp = $end->getTimestamp();
        $rangeDurationSeconds = $rangeEndTimestamp - $rangeStartTimestamp;
        $todayDate = today('Asia/Manila')->toDateString();
        $legendStatuses = [
            ['status' => 'pending', 'icon' => 'hourglass-split'],
            ['status' => 'approved', 'icon' => 'check-circle'],
            ['status' => 'ongoing', 'icon' => 'car-front'],
            ['status' => 'completed', 'icon' => 'check2-circle'],
            ['status' => 'rejected', 'icon' => 'x-circle'],
            ['status' => 'cancelled', 'icon' => 'slash-circle'],
        ];
        $statusIcons = array_column($legendStatuses, 'icon', 'status');
    @endphp

    <main class="admin-calendar" id="main-content">
        @if (session('status'))
            <div class="admin-calendar-status" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <section class="admin-calendar-range" aria-label="Displayed calendar date range">
            <div class="admin-calendar-range-label">
                <span><i class="bi bi-calendar3" aria-hidden="true"></i> Schedule window</span>
                <strong>{{ $start->format('M d') }} – {{ $end->copy()->subDay()->format('M d, Y') }}</strong>
                <small>14 days · Asia/Manila</small>
            </div>
            <nav class="admin-calendar-navigation" aria-label="Calendar date range">
                <a class="admin-calendar-nav-button" href="{{ route('admin.calendar.index', ['start_date' => $start->copy()->subDays(14)->toDateString()]) }}" aria-label="Previous 14 days">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>Previous</span>
                </a>
                <a class="admin-calendar-nav-today" href="{{ route('admin.calendar.index', ['start_date' => $todayDate]) }}">
                    <i class="bi bi-calendar-day" aria-hidden="true"></i>
                    <span>Today</span>
                </a>
                <a class="admin-calendar-nav-button" href="{{ route('admin.calendar.index', ['start_date' => $start->copy()->addDays(14)->toDateString()]) }}" aria-label="Next 14 days">
                    <span>Next</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </nav>
        </section>

        <section class="admin-calendar-summary" aria-label="Current fleet status">
            <article class="admin-calendar-summary-card">
                <div class="admin-calendar-summary-heading">
                    <span>Total vehicles</span>
                    <i class="bi bi-car-front" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format($fleetStatus['total']) }}</strong>
                <small>Registered fleet</small>
            </article>
            <article class="admin-calendar-summary-card calendar-summary--available">
                <div class="admin-calendar-summary-heading">
                    <span>Available</span>
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format($fleetStatus['available']) }}</strong>
                <small>Available right now</small>
            </article>
            <article class="admin-calendar-summary-card calendar-summary--reserved">
                <div class="admin-calendar-summary-heading">
                    <span>Reserved</span>
                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format($fleetStatus['reserved']) }}</strong>
                <small>Approved bookings now</small>
            </article>
            <article class="admin-calendar-summary-card calendar-summary--ongoing">
                <div class="admin-calendar-summary-heading">
                    <span>Ongoing rentals</span>
                    <i class="bi bi-key" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format($fleetStatus['ongoing']) }}</strong>
                <small>Currently on the road</small>
            </article>
            <article class="admin-calendar-summary-card calendar-summary--maintenance">
                <div class="admin-calendar-summary-heading">
                    <span>Under maintenance</span>
                    <i class="bi bi-tools" aria-hidden="true"></i>
                </div>
                <strong>{{ number_format($fleetStatus['maintenance']) }}</strong>
                <small>Unavailable for service</small>
            </article>
        </section>

        <section class="admin-calendar-legend" aria-label="Timeline status legend">
            <span class="admin-calendar-legend-title">Timeline key</span>
            <ul>
                @foreach ($legendStatuses as $legend)
                    <li class="calendar-legend--{{ $legend['status'] }}">
                        <i class="bi bi-{{ $legend['icon'] }}" aria-hidden="true"></i>
                        <span>{{ ucfirst($legend['status']) }}</span>
                    </li>
                @endforeach
                <li class="calendar-legend--maintenance">
                    <i class="bi bi-tools" aria-hidden="true"></i>
                    <span>Maintenance</span>
                </li>
            </ul>
        </section>

        <section class="admin-calendar-schedule-panel" aria-labelledby="calendar-schedule-heading">
            <header class="admin-calendar-schedule-heading">
                <div>
                    <p class="admin-calendar-eyebrow">14-day rolling schedule</p>
                    <h2 id="calendar-schedule-heading">Vehicle timeline</h2>
                </div>
                <span><i class="bi bi-clock" aria-hidden="true"></i> Times shown in Asia/Manila</span>
            </header>

            @if ($vehicles->isEmpty())
                <div class="admin-calendar-empty">
                    <i class="bi bi-car-front" aria-hidden="true"></i>
                    <strong>No vehicles to display</strong>
                    <span>Add vehicles to the fleet to view their schedule.</span>
                </div>
            @else
                <div class="admin-calendar-timeline-scroll" role="region" aria-label="Fleet scheduling timeline; scroll horizontally to view all dates" tabindex="0">
                    <div class="admin-calendar-timeline">
                        <div class="admin-calendar-timeline-header">
                            <div class="admin-calendar-vehicle-column-heading">Fleet / vehicle</div>
                            <div class="admin-calendar-date-axis" aria-label="Dates in the displayed schedule">
                                @foreach ($days as $day)
                                    <div class="admin-calendar-date-cell {{ $day->toDateString() === $todayDate ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}">
                                        <span>{{ $day->format('D') }}</span>
                                        <strong>{{ $day->format('d') }}</strong>
                                        <small>{{ $day->format('M') }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        @foreach ($vehicles as $vehicle)
                            @php
                                $timelineEvents = [];

                                foreach ($vehicle->bookings as $booking) {
                                    $periodStart = $booking->pickup_datetime->copy()->timezone('Asia/Manila');
                                    $periodEnd = $booking->return_datetime->copy()->timezone('Asia/Manila');
                                    $visibleStart = max($periodStart->getTimestamp(), $rangeStartTimestamp);
                                    $visibleEnd = min($periodEnd->getTimestamp(), $rangeEndTimestamp);

                                    $timelineEvents[] = [
                                        'kind' => 'booking',
                                        'model' => $booking,
                                        'start' => $periodStart,
                                        'end' => $periodEnd,
                                        'visible_start' => $visibleStart,
                                        'visible_end' => $visibleEnd,
                                        'left' => (($visibleStart - $rangeStartTimestamp) / $rangeDurationSeconds) * 100,
                                        'width' => (($visibleEnd - $visibleStart) / $rangeDurationSeconds) * 100,
                                    ];
                                }

                                foreach ($vehicle->unavailabilities as $unavailability) {
                                    $periodStart = $unavailability->start_datetime->copy()->timezone('Asia/Manila');
                                    $periodEnd = $unavailability->end_datetime->copy()->timezone('Asia/Manila');
                                    $visibleStart = max($periodStart->getTimestamp(), $rangeStartTimestamp);
                                    $visibleEnd = min($periodEnd->getTimestamp(), $rangeEndTimestamp);

                                    $timelineEvents[] = [
                                        'kind' => 'maintenance',
                                        'model' => $unavailability,
                                        'start' => $periodStart,
                                        'end' => $periodEnd,
                                        'visible_start' => $visibleStart,
                                        'visible_end' => $visibleEnd,
                                        'left' => (($visibleStart - $rangeStartTimestamp) / $rangeDurationSeconds) * 100,
                                        'width' => (($visibleEnd - $visibleStart) / $rangeDurationSeconds) * 100,
                                    ];
                                }

                                usort($timelineEvents, fn (array $first, array $second): int => $first['visible_start'] <=> $second['visible_start']);
                                $laneEnds = [];

                                foreach ($timelineEvents as $eventIndex => $event) {
                                    $lane = 0;
                                    while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $event['visible_start']) {
                                        $lane++;
                                    }

                                    $laneEnds[$lane] = $event['visible_end'];
                                    $timelineEvents[$eventIndex]['lane'] = $lane;
                                }

                                $laneCount = max(count($laneEnds), 1);
                            @endphp

                            <article class="admin-calendar-timeline-row" aria-labelledby="calendar-vehicle-{{ $loop->index }}">
                                <div class="admin-calendar-vehicle-cell">
                                    <span class="admin-calendar-vehicle-image">
                                        @if ($vehicle->image_path)
                                            <img src="{{ asset($vehicle->image_path) }}" alt="" loading="lazy">
                                        @else
                                            <i class="bi bi-car-front" aria-hidden="true"></i>
                                        @endif
                                    </span>
                                    <span class="admin-calendar-vehicle-details">
                                        <strong id="calendar-vehicle-{{ $loop->index }}">{{ $vehicle->name }}</strong>
                                        <span>{{ ucfirst($vehicle->category) }}</span>
                                    </span>
                                    <span class="admin-calendar-vehicle-state admin-calendar-vehicle-state--{{ $vehicle->status }}">
                                        <i class="bi bi-circle-fill" aria-hidden="true"></i>
                                        {{ ucfirst($vehicle->status) }}
                                    </span>
                                </div>

                                <div class="admin-calendar-track" @style(['min-height: '.($laneCount * 36 + 16).'px' => true])>
                                    @if (empty($timelineEvents))
                                        <span class="admin-calendar-track-empty">No scheduled bookings or maintenance</span>
                                    @endif

                                    @foreach ($timelineEvents as $event)
                                        @php
                                            $eventModel = $event['model'];
                                            $eventStatus = $event['kind'] === 'booking' ? $eventModel->status : 'maintenance';
                                            $eventLeft = number_format($event['left'], 4, '.', '');
                                            $eventWidth = number_format($event['width'], 4, '.', '');
                                            $eventTop = 8 + ($event['lane'] * 36);
                                            $isTooltipRightAligned = $event['left'] > 58;
                                            $tooltipId = 'calendar-event-'.$event['kind'].'-'.$eventModel->getKey();
                                        @endphp

                                        <div
                                            class="admin-calendar-event {{ $isTooltipRightAligned ? 'admin-calendar-event--right' : '' }}"
                                            @style([
                                                'left: '.$eventLeft.'%' => true,
                                                'width: '.$eventWidth.'%' => true,
                                                'top: '.$eventTop.'px' => true,
                                            ])
                                        >
                                            @if ($event['kind'] === 'booking')
                                                <a
                                                    class="admin-calendar-event-bar calendar-state--{{ $eventStatus }}"
                                                    href="{{ route('bookings.show', $eventModel) }}"
                                                    aria-label="View {{ $eventModel->booking_reference }}, {{ ucfirst($eventStatus) }}"
                                                    aria-describedby="{{ $tooltipId }}"
                                                    title="{{ $eventModel->booking_reference }} · {{ ucfirst($eventStatus) }} · {{ $event['start']->format('M d, g:i A') }} – {{ $event['end']->format('M d, g:i A') }}"
                                                >
                                                    <i class="bi bi-{{ $statusIcons[$eventStatus] ?? 'calendar-check' }}" aria-hidden="true"></i>
                                                    @if ($event['width'] >= 8)
                                                        <span>{{ $eventModel->booking_reference }}</span>
                                                    @endif
                                                </a>
                                            @else
                                                <span
                                                    class="admin-calendar-event-bar calendar-state--maintenance"
                                                    tabindex="0"
                                                    aria-label="Maintenance: {{ $eventModel->reason }}, {{ $event['start']->format('M d, g:i A') }} to {{ $event['end']->format('M d, g:i A') }}"
                                                    aria-describedby="{{ $tooltipId }}"
                                                    title="Maintenance · {{ $eventModel->reason }} · {{ $event['start']->format('M d, g:i A') }} – {{ $event['end']->format('M d, g:i A') }}"
                                                >
                                                    <i class="bi bi-tools" aria-hidden="true"></i>
                                                    @if ($event['width'] >= 10)
                                                        <span>Maintenance</span>
                                                    @endif
                                                </span>
                                            @endif

                                            <div class="admin-calendar-event-tooltip" id="{{ $tooltipId }}" role="group">
                                                <strong>{{ $event['kind'] === 'booking' ? $eventModel->booking_reference : 'Maintenance' }}</strong>
                                                @if ($event['kind'] === 'booking')
                                                    <span>{{ ucfirst($eventStatus) }}</span>
                                                @else
                                                    <span>{{ $eventModel->reason }}</span>
                                                @endif
                                                <span>{{ $event['start']->format('M d, g:i A') }} – {{ $event['end']->format('M d, g:i A') }}</span>
                                                @if ($event['kind'] === 'maintenance')
                                                    @can('manage', \App\Models\Vehicle::class)
                                                        <form method="POST" action="{{ route('admin.vehicle-unavailabilities.destroy', $eventModel) }}" onsubmit="return confirm('Remove this maintenance period? This change will be logged.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="admin-calendar-remove" type="submit" aria-label="Remove maintenance period for {{ $vehicle->name }}">
                                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                                                <span>Remove maintenance</span>
                                                            </button>
                                                        </form>
                                                    @endcan
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        @can('manage', \App\Models\Vehicle::class)
            @if ($vehicles->isNotEmpty())
                <section class="admin-calendar-maintenance" aria-labelledby="maintenance-heading">
                    <div class="admin-calendar-maintenance-heading">
                        <span class="admin-calendar-maintenance-icon" aria-hidden="true"><i class="bi bi-tools"></i></span>
                        <div>
                            <p class="admin-calendar-eyebrow">Fleet operations</p>
                            <h2 id="maintenance-heading">Add vehicle maintenance period</h2>
                            <p class="admin-calendar-muted">Temporarily block a vehicle during a service window. Times use Asia/Manila.</p>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="admin-calendar-errors" role="alert">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form class="admin-calendar-maintenance-form" method="POST" action="{{ route('admin.vehicle-unavailabilities.store') }}">
                        @csrf
                        <label>
                            <span><i class="bi bi-car-front" aria-hidden="true"></i> Vehicle</span>
                            <select name="vehicle_id" required>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" @selected(old('vehicle_id', $vehicles->first()->id) === $vehicle->id)>{{ $vehicle->name }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <small>{{ $message }}</small> @enderror
                        </label>
                        <label>
                            <span><i class="bi bi-calendar-event" aria-hidden="true"></i> Start</span>
                            <input type="datetime-local" name="start_datetime" value="{{ old('start_datetime') }}" required>
                            @error('start_datetime') <small>{{ $message }}</small> @enderror
                        </label>
                        <label>
                            <span><i class="bi bi-calendar-event" aria-hidden="true"></i> End</span>
                            <input type="datetime-local" name="end_datetime" value="{{ old('end_datetime') }}" required>
                            @error('end_datetime') <small>{{ $message }}</small> @enderror
                        </label>
                        <label>
                            <span><i class="bi bi-chat-left-text" aria-hidden="true"></i> Reason</span>
                            <input type="text" name="reason" maxlength="255" value="{{ old('reason') }}" placeholder="e.g. Scheduled service" required>
                            @error('reason') <small>{{ $message }}</small> @enderror
                        </label>
                        <button class="admin-calendar-button" type="submit">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            <span>Add maintenance period</span>
                        </button>
                    </form>
                </section>
            @endif
        @endcan
    </main>
@endsection
