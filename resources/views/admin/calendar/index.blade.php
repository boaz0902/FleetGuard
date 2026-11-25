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
                <button class="admin-calendar-mobile-legend" type="button" data-calendar-legend-open>
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>Legend</span>
                </button>
            </header>

            @if ($vehicles->isEmpty())
                <div class="admin-calendar-empty">
                    <i class="bi bi-car-front" aria-hidden="true"></i>
                    <strong>No vehicles to display</strong>
                    <span>Add vehicles to the fleet to view their schedule.</span>
                </div>
            @else
                @php
                    $mobileAgendaVehicles = [];
                    $mobileDayKeys = $days->map(fn ($day) => $day->toDateString())->all();
                    $mobileSelectedDate = in_array($todayDate, $mobileDayKeys, true)
                        ? $todayDate
                        : $days->first()->toDateString();
                @endphp
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
                                $eventsByDate = [];

                                foreach ($days as $agendaDay) {
                                    $agendaDayStart = $agendaDay->copy()->startOfDay();
                                    $agendaDayEnd = $agendaDayStart->copy()->addDay();
                                    $eventsByDate[$agendaDay->toDateString()] = array_values(array_filter(
                                        $timelineEvents,
                                        fn (array $event): bool => $event['start']->lt($agendaDayEnd)
                                            && $event['end']->gt($agendaDayStart),
                                    ));
                                }

                                $mobileAgendaVehicles[] = [
                                    'vehicle' => $vehicle,
                                    'events_by_date' => $eventsByDate,
                                    'timeline_events' => $timelineEvents,
                                ];
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
                                            $isTooltipRightAligned = ($event['left'] + $event['width']) > 70;
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
                                                    href="{{ route('admin.bookings.show', ['booking' => $eventModel, 'calendar_start_date' => $start->toDateString()]) }}"
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
                                                    <span>{{ $eventModel->customer?->full_name ?? 'Customer record unavailable' }}</span>
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

                @php
                    $mobileDaySummaries = [];
                    $mobileBookingDates = [];

                    foreach ($days as $agendaDay) {
                        $dateKey = $agendaDay->toDateString();
                        $summary = ['booked' => 0, 'available' => 0, 'maintenance' => 0];
                        $hasBookings = false;

                        foreach ($mobileAgendaVehicles as $agendaVehicle) {
                            $dayEvents = $agendaVehicle['events_by_date'][$dateKey] ?? [];
                            $bookingCount = count(array_filter($dayEvents, fn (array $event): bool => $event['kind'] === 'booking'));
                            $maintenanceCount = count(array_filter($dayEvents, fn (array $event): bool => $event['kind'] === 'maintenance'));
                            $hasBookings = $hasBookings || $bookingCount > 0;

                            if ($maintenanceCount > 0) {
                                $summary['maintenance']++;
                            } elseif ($bookingCount > 0) {
                                $summary['booked']++;
                            } else {
                                $summary['available']++;
                            }
                        }

                        $mobileDaySummaries[$dateKey] = $summary;
                        $mobileBookingDates[$dateKey] = $hasBookings;
                    }
                @endphp

                <div class="admin-calendar-mobile-agenda" data-calendar-agenda data-selected-date="{{ $mobileSelectedDate }}">
                    <div class="admin-calendar-date-strip" role="group" aria-label="Select a schedule day">
                        @foreach ($days as $day)
                            @php
                                $dateKey = $day->toDateString();
                                $isSelected = $dateKey === $mobileSelectedDate;
                                $hasBookings = $mobileBookingDates[$dateKey] ?? false;
                            @endphp
                            <button
                                class="admin-calendar-date-chip {{ $isSelected ? 'is-selected' : '' }}"
                                type="button"
                                data-calendar-date="{{ $dateKey }}"
                                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                aria-label="{{ $day->format('l, F j') }}{{ $hasBookings ? ', has bookings' : '' }}"
                            >
                                <span>{{ $day->format('D') }}</span>
                                <strong>{{ $day->format('d') }}</strong>
                                <small>{{ $day->format('M') }}</small>
                                @if ($hasBookings)
                                    <i class="admin-calendar-date-dot" aria-hidden="true"></i>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <div class="admin-calendar-day-summary" aria-live="polite">
                        <span class="calendar-day-summary--booked"><i class="bi bi-calendar-check" aria-hidden="true"></i>Booked <strong data-calendar-summary="booked">{{ $mobileDaySummaries[$mobileSelectedDate]['booked'] ?? 0 }}</strong></span>
                        <span class="calendar-day-summary--available"><i class="bi bi-check-circle" aria-hidden="true"></i>Available <strong data-calendar-summary="available">{{ $mobileDaySummaries[$mobileSelectedDate]['available'] ?? 0 }}</strong></span>
                        <span class="calendar-day-summary--maintenance"><i class="bi bi-tools" aria-hidden="true"></i>Maintenance <strong data-calendar-summary="maintenance">{{ $mobileDaySummaries[$mobileSelectedDate]['maintenance'] ?? 0 }}</strong></span>
                    </div>

                    <div class="admin-calendar-agenda-list">
                        @foreach ($mobileAgendaVehicles as $agendaVehicle)
                            @php
                                $agendaVehicleModel = $agendaVehicle['vehicle'];
                                $selectedDayEvents = $agendaVehicle['events_by_date'][$mobileSelectedDate] ?? [];
                            @endphp
                            <article class="admin-calendar-agenda-card" data-calendar-vehicle-card>
                                <button
                                    class="admin-calendar-agenda-vehicle"
                                    type="button"
                                    data-calendar-vehicle-open
                                    data-calendar-vehicle-name="{{ $agendaVehicleModel->name }}"
                                    aria-label="View the 14-day schedule for {{ $agendaVehicleModel->name }}"
                                >
                                    <span class="admin-calendar-agenda-photo">
                                        @if ($agendaVehicleModel->image_path)
                                            <img src="{{ asset($agendaVehicleModel->image_path) }}" alt="" loading="lazy">
                                        @else
                                            <i class="bi bi-car-front" aria-hidden="true"></i>
                                        @endif
                                    </span>
                                    <span class="admin-calendar-agenda-vehicle-copy">
                                        <strong>{{ $agendaVehicleModel->name }}</strong>
                                        <span>{{ ucfirst($agendaVehicleModel->category) }}</span>
                                    </span>
                                    <span class="admin-calendar-vehicle-state admin-calendar-vehicle-state--{{ $agendaVehicleModel->status }}">
                                        <i class="bi bi-circle-fill" aria-hidden="true"></i>
                                        {{ ucfirst($agendaVehicleModel->status) }}
                                    </span>
                                    <i class="bi bi-chevron-right admin-calendar-agenda-chevron" aria-hidden="true"></i>
                                </button>

                                <div class="admin-calendar-availability-heading">
                                    <span>Availability</span>
                                    <span>Next 14 days</span>
                                </div>
                                <div class="admin-calendar-availability-strip" aria-label="14-day availability">
                                    @foreach ($days as $day)
                                        @php
                                            $dateKey = $day->toDateString();
                                            $dayEvents = $agendaVehicle['events_by_date'][$dateKey] ?? [];
                                            $hasBookings = count(array_filter($dayEvents, fn (array $event): bool => $event['kind'] === 'booking')) > 0;
                                            $hasMaintenance = count(array_filter($dayEvents, fn (array $event): bool => $event['kind'] === 'maintenance')) > 0;
                                        @endphp
                                        <span
                                            class="admin-calendar-availability-day {{ $hasMaintenance ? 'is-maintenance' : ($hasBookings ? 'is-booked' : 'is-free') }} {{ $dateKey === $mobileSelectedDate ? 'is-selected' : '' }}"
                                            data-calendar-strip-date="{{ $dateKey }}"
                                            title="{{ $day->format('D, M j') }}: {{ $hasMaintenance ? 'Maintenance' : ($hasBookings ? 'Booked' : 'Available') }}"
                                        ></span>
                                    @endforeach
                                </div>

                                <div class="admin-calendar-agenda-day-events" data-calendar-selected-events>
                                    @if ($selectedDayEvents)
                                        @foreach ($selectedDayEvents as $agendaEvent)
                                            @php
                                                $agendaEventModel = $agendaEvent['model'];
                                                $agendaStatusKey = $agendaEvent['kind'] === 'booking' ? $agendaEventModel->status : 'maintenance';
                                                $agendaStatusLabel = $agendaEvent['kind'] === 'booking' ? ucfirst($agendaStatusKey) : 'Maintenance';
                                                $agendaStatusIcon = $agendaEvent['kind'] === 'booking' ? ($statusIcons[$agendaStatusKey] ?? 'calendar-check') : 'tools';
                                                $agendaDate = \Illuminate\Support\Carbon::parse($mobileSelectedDate, 'Asia/Manila');
                                                $agendaDayStart = $agendaDate->copy()->startOfDay();
                                                $agendaDayEnd = $agendaDayStart->copy()->addDay();
                                                $agendaTimeStart = $agendaEvent['start']->getTimestamp() < $agendaDayStart->getTimestamp() ? 'Earlier' : $agendaEvent['start']->format('g:i A');
                                                $agendaTimeEnd = $agendaEvent['end']->getTimestamp() > $agendaDayEnd->getTimestamp() ? 'Continues' : $agendaEvent['end']->format('g:i A');
                                                $agendaReference = $agendaEvent['kind'] === 'booking' ? $agendaEventModel->booking_reference : $agendaEventModel->reason;
                                            @endphp
                                            @if ($agendaEvent['kind'] === 'booking')
                                                <a
                                                    class="admin-calendar-agenda-event"
                                                    href="{{ route('admin.bookings.show', ['booking' => $agendaEventModel, 'calendar_start_date' => $start->toDateString()]) }}"
                                                    aria-label="View reservation {{ $agendaEventModel->booking_reference }}"
                                                    data-calendar-agenda-event
                                                    data-calendar-kind="booking"
                                                >
                                            @else
                                                <button
                                                    class="admin-calendar-agenda-event"
                                                    type="button"
                                                    data-calendar-agenda-event
                                                    data-calendar-kind="maintenance"
                                                    data-calendar-status-key="{{ $agendaStatusKey }}"
                                                    data-calendar-status-label="{{ $agendaStatusLabel }}"
                                                    data-calendar-icon="{{ $agendaStatusIcon }}"
                                                    data-calendar-reference="{{ $agendaReference }}"
                                                    data-calendar-reason="{{ $agendaEventModel->reason }}"
                                                    data-calendar-start="{{ $agendaEvent['start']->format('M d, Y · g:i A') }}"
                                                    data-calendar-end="{{ $agendaEvent['end']->format('M d, Y · g:i A') }}"
                                                    data-calendar-vehicle="{{ $agendaVehicleModel->name }}"
                                                >
                                            @endif
                                                <span class="admin-calendar-agenda-event-copy">
                                                    <strong>{{ $agendaEvent['kind'] === 'booking' ? $agendaEventModel->booking_reference : $agendaEventModel->reason }}</strong>
                                                    <span>{{ $agendaTimeStart }} – {{ $agendaTimeEnd }}</span>
                                                </span>
                                                <span class="admin-calendar-agenda-status calendar-agenda-status--{{ $agendaStatusKey }}">
                                                    <i class="bi bi-{{ $agendaStatusIcon }}" aria-hidden="true"></i>
                                                    {{ $agendaStatusLabel }}
                                                </span>
                                            @if ($agendaEvent['kind'] === 'booking')
                                                </a>
                                            @else
                                                </button>
                                            @endif
                                        @endforeach
                                    @else
                                        <p class="admin-calendar-agenda-available"><i class="bi bi-check2" aria-hidden="true"></i>Available on this day</p>
                                    @endif
                                </div>

                                <template data-calendar-vehicle-schedule>
                                    @foreach ($days as $day)
                                        @php
                                            $dateKey = $day->toDateString();
                                            $dayStart = $day->copy()->startOfDay();
                                            $dayEnd = $dayStart->copy()->addDay();
                                            $dayEvents = $agendaVehicle['events_by_date'][$dateKey] ?? [];
                                        @endphp
                                        <section class="admin-calendar-sheet-date-group" data-calendar-date-group="{{ $dateKey }}">
                                            <h3>{{ $day->format('l, F j') }}</h3>
                                            <div class="admin-calendar-sheet-date-list" data-calendar-date-list>
                                                @forelse ($dayEvents as $agendaEvent)
                                                    @php
                                                        $agendaEventModel = $agendaEvent['model'];
                                                        $agendaStatusKey = $agendaEvent['kind'] === 'booking' ? $agendaEventModel->status : 'maintenance';
                                                        $agendaStatusLabel = $agendaEvent['kind'] === 'booking' ? ucfirst($agendaStatusKey) : 'Maintenance';
                                                        $agendaStatusIcon = $agendaEvent['kind'] === 'booking' ? ($statusIcons[$agendaStatusKey] ?? 'calendar-check') : 'tools';
                                                        $agendaTimeStart = $agendaEvent['start']->getTimestamp() < $dayStart->getTimestamp() ? 'Earlier' : $agendaEvent['start']->format('g:i A');
                                                        $agendaTimeEnd = $agendaEvent['end']->getTimestamp() > $dayEnd->getTimestamp() ? 'Continues' : $agendaEvent['end']->format('g:i A');
                                                        $agendaReference = $agendaEvent['kind'] === 'booking' ? $agendaEventModel->booking_reference : $agendaEventModel->reason;
                                                    @endphp
                                                    @if ($agendaEvent['kind'] === 'booking')
                                                        <a
                                                            class="admin-calendar-agenda-event"
                                                            href="{{ route('admin.bookings.show', ['booking' => $agendaEventModel, 'calendar_start_date' => $start->toDateString()]) }}"
                                                            aria-label="View reservation {{ $agendaEventModel->booking_reference }}"
                                                            data-calendar-agenda-event
                                                            data-calendar-kind="booking"
                                                        >
                                                    @else
                                                        <button
                                                            class="admin-calendar-agenda-event"
                                                            type="button"
                                                            data-calendar-agenda-event
                                                            data-calendar-kind="maintenance"
                                                            data-calendar-status-key="{{ $agendaStatusKey }}"
                                                            data-calendar-status-label="{{ $agendaStatusLabel }}"
                                                            data-calendar-icon="{{ $agendaStatusIcon }}"
                                                            data-calendar-reference="{{ $agendaReference }}"
                                                            data-calendar-reason="{{ $agendaEventModel->reason }}"
                                                            data-calendar-start="{{ $agendaEvent['start']->format('M d, Y · g:i A') }}"
                                                            data-calendar-end="{{ $agendaEvent['end']->format('M d, Y · g:i A') }}"
                                                            data-calendar-vehicle="{{ $agendaVehicleModel->name }}"
                                                        >
                                                    @endif
                                                        <span class="admin-calendar-agenda-event-copy">
                                                            <strong>{{ $agendaEvent['kind'] === 'booking' ? $agendaEventModel->booking_reference : $agendaEventModel->reason }}</strong>
                                                            <span>{{ $agendaTimeStart }} – {{ $agendaTimeEnd }}</span>
                                                        </span>
                                                        <span class="admin-calendar-agenda-status calendar-agenda-status--{{ $agendaStatusKey }}">
                                                            <i class="bi bi-{{ $agendaStatusIcon }}" aria-hidden="true"></i>
                                                            {{ $agendaStatusLabel }}
                                                        </span>
                                                    @if ($agendaEvent['kind'] === 'booking')
                                                        </a>
                                                    @else
                                                        </button>
                                                    @endif
                                                @empty
                                                    <p class="admin-calendar-agenda-available"><i class="bi bi-check2" aria-hidden="true"></i>Available on this day</p>
                                                @endforelse
                                            </div>
                                        </section>
                                    @endforeach
                                </template>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <dialog class="admin-calendar-mobile-sheet" data-calendar-mobile-sheet aria-labelledby="calendar-mobile-sheet-title">
            <div class="admin-calendar-sheet-handle" aria-hidden="true"><span></span></div>
            <header class="admin-calendar-sheet-heading">
                <div>
                    <p class="admin-calendar-eyebrow">Fleet availability</p>
                    <h2 id="calendar-mobile-sheet-title" data-calendar-sheet-title>Schedule details</h2>
                </div>
                <button class="admin-calendar-sheet-close" type="button" data-calendar-sheet-close aria-label="Close details">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>
            <div class="admin-calendar-sheet-content" data-calendar-sheet-content></div>
        </dialog>

        <template data-calendar-legend-template>
            <ul class="admin-calendar-sheet-legend">
                @foreach ($legendStatuses as $legend)
                    <li class="calendar-legend--{{ $legend['status'] }}">
                        <i class="bi bi-{{ $legend['icon'] }}" aria-hidden="true"></i>
                        <span>{{ ucfirst($legend['status']) }}</span>
                    </li>
                @endforeach
                <li class="calendar-legend--maintenance"><i class="bi bi-tools" aria-hidden="true"></i><span>Maintenance</span></li>
                <li class="calendar-legend--available"><i class="bi bi-check-circle" aria-hidden="true"></i><span>Available</span></li>
            </ul>
        </template>

        @can('manage', \App\Models\Vehicle::class)
            @include('admin.partials.vehicle-maintenance-form', [
                'maintenanceVehicles' => $vehicles,
                'maintenanceAction' => route('admin.vehicle-unavailabilities.store'),
            ])
        @endcan
    </main>
@endsection
