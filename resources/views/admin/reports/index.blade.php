@extends('layouts.admin')

@section('title', 'Reports & Analytics | FleetGuard')
@section('admin-page-title', 'Reports & Analytics')
@section('admin-page-icon', 'bar-chart')
@section('admin-breadcrumb', 'Management')
@section('admin-page-description', 'Read-only reports across reservations, fleet, customers, and rentals')

@section('admin-content')
    @php
        $statusColors = [
            'pending' => '#c49a3a',
            'approved' => '#3988d6',
            'ongoing' => '#3b9471',
            'completed' => '#89939f',
            'rejected' => '#b85c55',
            'cancelled' => '#b9c1c9',
        ];
        $chartPalette = ['#3988d6', '#3b9471', '#c49a3a', '#89939f', '#101820'];
        $donutOffset = 0;
        $statusSlices = [];
        $statusTotal = (int) $overview['total_reservations'];

        foreach ($status_breakdown as $status) {
            $percentage = $statusTotal > 0 ? ($status['count'] / $statusTotal) * 100 : 0;
            $statusSlices[] = [...$status, 'percentage' => $percentage, 'offset' => $donutOffset];
            $donutOffset += $percentage;
        }
        $statusSummary = collect($statusSlices)
            ->map(fn (array $slice) => $slice['label'].' '.number_format($slice['count']).' ('.number_format($slice['percentage'], 1).'%)')
            ->implode(', ');

        $categoryTotal = (int) $popular_categories->sum('booking_count');
        $donutOffset = 0;
        $categorySlices = [];

        foreach ($popular_categories as $category) {
            $percentage = $categoryTotal > 0 ? ($category->booking_count / $categoryTotal) * 100 : 0;
            $categorySlices[] = [
                'label' => ucfirst($category->category),
                'count' => (int) $category->booking_count,
                'percentage' => $percentage,
                'offset' => $donutOffset,
            ];
            $donutOffset += $percentage;
        }
        $categorySummary = collect($categorySlices)
            ->map(fn (array $slice) => $slice['label'].' '.$slice['count'].' ('.number_format($slice['percentage'], 1).'%)')
            ->implode(', ');

        $customerTotal = $customers['new_records'] + $customers['returning_customers'];
        $customerSlices = [
            [
                'label' => 'New records',
                'count' => (int) $customers['new_records'],
                'percentage' => $customerTotal > 0 ? ($customers['new_records'] / $customerTotal) * 100 : 0,
                'offset' => 0,
                'color' => '#3988d6',
            ],
            [
                'label' => 'Returning records',
                'count' => (int) $customers['returning_customers'],
                'percentage' => $customerTotal > 0 ? ($customers['returning_customers'] / $customerTotal) * 100 : 0,
                'offset' => $customerTotal > 0 ? ($customers['new_records'] / $customerTotal) * 100 : 0,
                'color' => '#89939f',
            ],
        ];

        $trendMaximum = max(1, max(array_column($trend, 'count') ?: [0]));
        $trendPoints = [];
        $plotLeft = 52;
        $plotRight = 744;
        $plotTop = 28;
        $plotBottom = 218;

        foreach ($trend as $index => $point) {
            $x = count($trend) > 1
                ? $plotLeft + ($index / (count($trend) - 1)) * ($plotRight - $plotLeft)
                : ($plotLeft + $plotRight) / 2;
            $y = $plotBottom - (((int) $point['count'] / $trendMaximum) * ($plotBottom - $plotTop));
            $trendPoints[] = [...$point, 'x' => $x, 'y' => $y];
        }

        $trendLinePath = '';
        $previousPoint = null;

        foreach ($trendPoints as $point) {
            if ($previousPoint === null) {
                $trendLinePath = 'M '.$point['x'].' '.$point['y'];
            } else {
                $midpoint = ($previousPoint['x'] + $point['x']) / 2;
                $midpointY = ($previousPoint['y'] + $point['y']) / 2;
                $trendLinePath .= ' Q '.$previousPoint['x'].' '.$previousPoint['y'].' '.$midpoint.' '.$midpointY;
            }

            $previousPoint = $point;
        }

        if (count($trendPoints) > 1) {
            $lastTrendPoint = end($trendPoints);
            $trendLinePath .= ' L '.$lastTrendPoint['x'].' '.$lastTrendPoint['y'];
        }

        $trendAreaPath = count($trendPoints) > 1
            ? $trendLinePath.' L '.$plotRight.' '.$plotBottom.' L '.$plotLeft.' '.$plotBottom.' Z'
            : '';
        $maxPopularBookings = max(1, (int) $popular_vehicles->max('booking_count'));
        $maxMaintenanceDays = max(1, (float) $fleet['vehicles']->max('maintenance_days'));
        $reportRangeDays = max(1, (int) $fleet['range_days']);
        $highestUtilizationVehicle = $fleet['vehicles']->sortByDesc('utilization')->first();
        $busiestVehicle = $popular_vehicles->first();
        $fleetUtilization = min(100, max(0, (float) $fleet['utilization']));
        $revenueTotal = max(0, (float) $overview['estimated_revenue']);
        $completedRevenue = max(0, (float) $overview['completed_estimated_revenue']);
        $revenueCompletedWidth = $revenueTotal > 0 ? min(100, ($completedRevenue / $revenueTotal) * 100) : 0;
        $ongoingCount = (int) (collect($status_breakdown)->firstWhere('status', 'ongoing')['count'] ?? 0);
        $completedWidth = $overview['total_reservations'] > 0
            ? min(100, ($operations['completed_rentals'] / $overview['total_reservations']) * 100)
            : 0;
        $overdueWidth = $ongoingCount > 0 ? min(100, ($operations['overdue_returns'] / $ongoingCount) * 100) : 0;
        $auditCoverageWidth = $operations['completed_rentals'] > 0
            ? min(100, ($operations['paired_audit_rentals'] / $operations['completed_rentals']) * 100)
            : 0;
    @endphp

    <main class="admin-content admin-reports-page" id="main-content">
        <section class="admin-page-intro admin-report-header">
            <div class="admin-report-header-copy">
                <p class="admin-eyebrow">Business intelligence</p>
                <h2>Performance at a glance</h2>
                <p class="admin-report-subtitle">A read-only view of reservations, fleet performance, and rental operations.</p>
                <p class="admin-report-range-line" aria-label="Selected date range">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    @if ($date_range['start']->isSameMonth($date_range['end']) && $date_range['start']->isSameYear($date_range['end']))
                        {{ $date_range['start']->format('M j') }} – {{ $date_range['end']->format('M j, Y') }}
                    @else
                        {{ $date_range['start']->format('M j, Y') }} – {{ $date_range['end']->format('M j, Y') }}
                    @endif
                    <span>{{ number_format($date_range['days']) }} calendar days</span>
                </p>
            </div>
            <div class="admin-report-header-actions">
                <p class="admin-report-last-updated">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    Last updated <time datetime="{{ $now->toIso8601String() }}">{{ $now->timezone('Asia/Manila')->format('M j, Y g:i A') }}</time>
                </p>
                <a class="admin-primary-link admin-report-export" href="{{ route('admin.reports.export', $filters) }}">
                    <i class="bi bi-download" aria-hidden="true"></i>
                    Export CSV
                </a>
            </div>
        </section>

        <section class="admin-panel admin-report-filters" aria-labelledby="report-filter-heading">
            <div class="admin-panel-heading">
                <div>
                    <p class="admin-eyebrow">Refine reports</p>
                    <h2 id="report-filter-heading">Report filters</h2>
                </div>
            </div>
            <form method="GET" action="{{ route('admin.reports.index') }}" class="admin-report-filter-form">
                <fieldset class="admin-report-preset-field">
                    <legend>Date preset</legend>
                    <div class="admin-report-preset-group" role="group" aria-label="Date preset">
                        @foreach ([
                            'this_month' => 'This month',
                            'last_month' => 'Last month',
                            'last_30_days' => '30 days',
                            'last_90_days' => '90 days',
                            'this_year' => 'This year',
                            'custom' => 'Custom',
                        ] as $rangeValue => $rangeLabel)
                            <label class="admin-report-preset-option">
                                <input type="radio" name="range" value="{{ $rangeValue }}" @checked($filters['range'] === $rangeValue)>
                                <span>{{ $rangeLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <label>
                    <span>Start date</span>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}">
                </label>
                <label>
                    <span>End date</span>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}">
                </label>
                <label>
                    <span>Vehicle category</span>
                    <select name="category">
                        <option value="">All categories</option>
                        @foreach ($category_options as $category)
                            <option value="{{ $category }}" @selected($filters['category'] === $category)>{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Vehicle</span>
                    <select name="vehicle_id">
                        <option value="">All vehicles</option>
                        @foreach ($vehicle_options as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected($filters['vehicle_id'] === $vehicle->id)>
                                {{ $vehicle->name }} · {{ ucfirst($vehicle->category) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <button class="admin-primary-link admin-report-apply" type="submit">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    Apply filters
                </button>
            </form>

            <p class="admin-report-active-range">
                <span>Active range</span>
                <strong>
                    @if ($date_range['start']->isSameMonth($date_range['end']) && $date_range['start']->isSameYear($date_range['end']))
                        {{ $date_range['start']->format('M j') }} – {{ $date_range['end']->format('M j, Y') }}
                    @else
                        {{ $date_range['start']->format('M j, Y') }} – {{ $date_range['end']->format('M j, Y') }}
                    @endif
                </strong>
                <small>{{ $filters['category'] ? ucfirst($filters['category']) : 'All categories' }} · {{ $filters['vehicle_id'] ? ($vehicle_options->firstWhere('id', $filters['vehicle_id'])?->name ?? $filters['vehicle_id']) : 'All vehicles' }}</small>
            </p>

            <div class="admin-report-filter-chips" aria-label="Active report filters">
                <span class="admin-report-filter-chip">
                    <span>{{ ucfirst(str_replace('_', ' ', $filters['range'])) }} · {{ $filters['start_date'] }} to {{ $filters['end_date'] }}</span>
                    <a href="{{ route('admin.reports.index', array_filter([
                        'range' => 'this_month',
                        'category' => $filters['category'],
                        'vehicle_id' => $filters['vehicle_id'],
                    ], fn ($value) => $value !== null && $value !== '')) }}"
                       aria-label="Reset date range to this month">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </a>
                </span>
                @if ($filters['category'])
                    <span class="admin-report-filter-chip">
                        <span>Category · {{ ucfirst($filters['category']) }}</span>
                        <a href="{{ route('admin.reports.index', array_filter([
                            'range' => $filters['range'],
                            'start_date' => $filters['start_date'],
                            'end_date' => $filters['end_date'],
                            'vehicle_id' => $filters['vehicle_id'],
                        ], fn ($value) => $value !== null && $value !== '')) }}"
                           aria-label="Remove category filter">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                    </span>
                @endif
                @if ($filters['vehicle_id'])
                    <span class="admin-report-filter-chip">
                        <span>Vehicle · {{ $vehicle_options->firstWhere('id', $filters['vehicle_id'])?->name ?? $filters['vehicle_id'] }}</span>
                        <a href="{{ route('admin.reports.index', array_filter([
                            'range' => $filters['range'],
                            'start_date' => $filters['start_date'],
                            'end_date' => $filters['end_date'],
                            'category' => $filters['category'],
                        ], fn ($value) => $value !== null && $value !== '')) }}"
                           aria-label="Remove vehicle filter">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                    </span>
                @endif
                @if ($filters['category'] || $filters['vehicle_id'] || $filters['range'] !== 'this_month')
                    <a class="admin-report-clear-filters" href="{{ route('admin.reports.index') }}">Clear filters</a>
                @endif
            </div>
            @if ($errors->any())
                <div class="admin-report-validation" role="alert">
                    <strong>Check the report filters:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <nav class="admin-report-section-nav" aria-label="Report sections" data-report-section-nav>
            <a href="#report-overview">Overview</a>
            <a href="#report-reservations">Trends</a>
            <a href="#report-fleet">Fleet</a>
            <a href="#report-customers">Customers</a>
            <a href="#report-maintenance">Maintenance</a>
            <a href="#report-operations">Rentals</a>
        </nav>

        <section class="admin-report-section" id="report-overview" aria-labelledby="report-overview-heading">
            <div class="admin-report-section-heading">
                <div>
                    <p class="admin-eyebrow">Business summary</p>
                    <h2 id="report-overview-heading">Overview</h2>
                </div>
                <span>Reservation metrics by pickup date</span>
            </div>
            <div class="admin-metrics admin-report-metrics" aria-label="Report overview metrics">
                <article class="admin-metric-card admin-report-revenue-card admin-report-revenue-hero">
                    <div class="admin-metric-heading">
                        <span>Estimated Revenue (from booking snapshots)</span>
                        <span class="admin-metric-icon metric-icon--green"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
                    </div>
                    <strong>₱{{ number_format($overview['estimated_revenue'], 2) }}</strong>
                    <small>Approved, ongoing, and completed snapshots · not payment data</small>
                    <div class="admin-report-revenue-compare" role="img"
                         aria-label="Completed-only estimated revenue ₱{{ number_format($overview['completed_estimated_revenue'], 2) }} compared with all-confirmed estimated revenue ₱{{ number_format($overview['estimated_revenue'], 2) }}">
                        <div class="admin-report-revenue-track">
                            <span class="admin-report-revenue-fill" style="width: {{ $revenueTotal > 0 ? 100 : 0 }}%" title="All confirmed: ₱{{ number_format($overview['estimated_revenue'], 2) }}" tabindex="0"></span>
                        </div>
                        <div class="admin-report-revenue-track admin-report-revenue-track--completed">
                            <span class="admin-report-revenue-fill" style="width: {{ $revenueCompletedWidth }}%" title="Completed only: ₱{{ number_format($overview['completed_estimated_revenue'], 2) }}" tabindex="0"></span>
                        </div>
                        <div class="admin-report-revenue-labels">
                            <span>All confirmed <strong>₱{{ number_format($overview['estimated_revenue'], 2) }}</strong></span>
                            <span>Completed only <strong>₱{{ number_format($overview['completed_estimated_revenue'], 2) }}</strong></span>
                        </div>
                    </div>
                </article>

                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Total reservations</span><span class="admin-metric-icon metric-icon--navy"><i class="bi bi-card-list" aria-hidden="true"></i></span></div>
                    <strong>{{ number_format($overview['total_reservations']) }}</strong>
                    <small>All statuses in selected range</small>
                </article>
                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Approved</span><span class="admin-metric-icon metric-icon--blue"><i class="bi bi-check-circle" aria-hidden="true"></i></span></div>
                    <strong>{{ number_format($overview['approved']) }}</strong>
                    <small>Current approved status</small>
                </article>
                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Completed</span><span class="admin-metric-icon metric-icon--green"><i class="bi bi-check2-circle" aria-hidden="true"></i></span></div>
                    <strong>{{ number_format($overview['completed']) }}</strong>
                    <small>Current completed status</small>
                </article>
                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Cancelled / rejected</span><span class="admin-metric-icon metric-icon--slate"><i class="bi bi-slash-circle" aria-hidden="true"></i></span></div>
                    <strong>{{ number_format($overview['cancelled_rejected']) }}</strong>
                    <small>Combined current statuses</small>
                </article>
                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Approval rate</span><span class="admin-metric-icon metric-icon--blue"><i class="bi bi-percent" aria-hidden="true"></i></span></div>
                    <strong>{{ number_format($overview['approval_rate'], 1) }}%</strong>
                    <small>{{ number_format($overview['approval_denominator']) }} decided; pending/cancelled excluded</small>
                </article>
                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Average booking duration</span><span class="admin-metric-icon metric-icon--navy"><i class="bi bi-calendar-range" aria-hidden="true"></i></span></div>
                    <strong>{{ $overview['average_duration_days'] === null ? '—' : number_format($overview['average_duration_days'], 1).' days' }}</strong>
                    <small>Confirmed booking duration snapshots</small>
                </article>
                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Average daily rate</span><span class="admin-metric-icon metric-icon--blue"><i class="bi bi-tag" aria-hidden="true"></i></span></div>
                    <strong>{{ $overview['average_daily_rate'] === null ? '—' : '₱'.number_format($overview['average_daily_rate'], 2) }}</strong>
                    <small>Confirmed booking rate snapshots</small>
                </article>
                <article class="admin-metric-card">
                    <div class="admin-metric-heading"><span>Fleet utilization</span><span class="admin-metric-icon metric-icon--amber"><i class="bi bi-speedometer" aria-hidden="true"></i></span></div>
                    <strong>{{ number_format($overview['fleet_utilization'], 1) }}%</strong>
                    <small>{{ number_format($fleet['rented_days'], 2) }} rented of {{ number_format($fleet['available_days'], 2) }} available fleet days</small>
                </article>
            </div>
            <p class="admin-report-footnote">
                Approval rate = approved, ongoing, and completed reservations divided by those statuses plus rejected; pending and cancelled are excluded.
                Revenue uses only stored booking totals/rate snapshots and is not collected income.
            </p>
        </section>

        <section class="admin-report-section" id="report-reservations" aria-labelledby="report-reservations-heading">
            <div class="admin-report-section-heading">
                <div>
                    <p class="admin-eyebrow">Volume and outcomes</p>
                    <h2 id="report-reservations-heading">Reservations trend</h2>
                </div>
                <span>Grouped by {{ $date_range['days'] <= 31 ? 'day' : ($date_range['days'] <= 180 ? 'week' : 'month') }}</span>
            </div>
            <div class="admin-report-chart-grid admin-report-chart-grid--trend">
                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-activity" aria-hidden="true"></i></span>
                        <div>
                            <h3>Reservation volume</h3>
                            <p>All booking statuses by pickup date</p>
                        </div>
                    </div>
                    @if ($overview['total_reservations'] > 0)
                        <div class="admin-report-trend-chart" role="img"
                             aria-label="Reservation trend has {{ number_format($overview['total_reservations']) }} booking{{ $overview['total_reservations'] === 1 ? '' : 's' }}, grouped by {{ $date_range['days'] <= 31 ? 'day' : ($date_range['days'] <= 180 ? 'week' : 'month') }}. Highest bucket: {{ number_format($trendMaximum) }}.">
                            <svg viewBox="0 0 760 270" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="report-trend-fill" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#3988d6" stop-opacity=".25"></stop>
                                        <stop offset="100%" stop-color="#3988d6" stop-opacity=".015"></stop>
                                    </linearGradient>
                                </defs>
                                @foreach ([['y' => 28, 'value' => $trendMaximum], ['y' => 123, 'value' => round($trendMaximum / 2, 1)], ['y' => 218, 'value' => 0]] as $gridline)
                                    <line class="admin-report-trend-gridline" x1="{{ $plotLeft }}" x2="{{ $plotRight }}" y1="{{ $gridline['y'] }}" y2="{{ $gridline['y'] }}"></line>
                                    <text class="admin-report-trend-axis-label" x="6" y="{{ $gridline['y'] + 4 }}">{{ $gridline['value'] }}</text>
                                @endforeach
                                @if ($trendAreaPath !== '')
                                    <path class="admin-report-trend-area" d="{{ $trendAreaPath }}"></path>
                                @endif
                                <path class="admin-report-trend-line" d="{{ $trendLinePath }}"></path>
                                @foreach ($trendPoints as $point)
                                    <circle class="admin-report-trend-point"
                                            cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4"
                                            tabindex="0" focusable="true"
                                        aria-label="{{ $point['label'] }}: {{ number_format($point['count']) }} reservation{{ (int) $point['count'] === 1 ? '' : 's' }}">
                                    <title>{{ $point['label'] }} · {{ $point['start'] === $point['end'] ? $point['start'] : $point['start'].' – '.$point['end'] }}: {{ number_format($point['count']) }} reservation{{ (int) $point['count'] === 1 ? '' : 's' }}</title>
                                    </circle>
                                @endforeach
                                @if (count($trendPoints) > 0)
                                    @foreach (array_unique([0, (int) floor((count($trendPoints) - 1) / 2), count($trendPoints) - 1]) as $tickIndex)
                                        <text class="admin-report-trend-date-label"
                                              x="{{ $trendPoints[$tickIndex]['x'] }}"
                                              y="253"
                                              text-anchor="{{ $tickIndex === 0 ? 'start' : ($tickIndex === count($trendPoints) - 1 ? 'end' : 'middle') }}">
                                            {{ $trendPoints[$tickIndex]['label'] }}
                                        </text>
                                    @endforeach
                                @endif
                            </svg>
                        </div>
                        <details class="admin-report-chart-data">
                            <summary>View reservations data table</summary>
                            <div class="admin-report-table-wrap">
                                <table class="admin-report-table">
                                    <thead><tr><th scope="col">Period</th><th scope="col">Reservations</th></tr></thead>
                                    <tbody>
                                        @foreach ($trend as $point)
                                            <tr><td>{{ $point['label'] }}</td><td>{{ number_format($point['count']) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @else
                        <div class="admin-report-empty admin-report-empty--chart">
                            <i class="bi bi-activity" aria-hidden="true"></i>
                            <strong>No bookings in this range.</strong>
                            <span>Try a wider date range or remove the vehicle filters.</span>
                        </div>
                    @endif
                </article>

                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-pie-chart" aria-hidden="true"></i></span>
                        <div>
                            <h3>Reservation status</h3>
                            <p>Current states in the selected range</p>
                        </div>
                    </div>
                    <div class="admin-report-donut-layout admin-report-status-layout">
                        <svg class="admin-report-donut" viewBox="0 0 120 120" role="img"
                             aria-label="Reservation status breakdown: {{ $statusSummary }}">
                            <circle class="admin-report-donut-track" cx="60" cy="60" r="42" pathLength="100"></circle>
                            @foreach ($statusSlices as $slice)
                                @if ($slice['percentage'] > 0)
                                    <circle class="admin-report-donut-segment" cx="60" cy="60" r="42" pathLength="100"
                                            stroke="{{ $statusColors[$slice['status']] ?? '#89939f' }}"
                                            stroke-dasharray="{{ number_format($slice['percentage'], 3, '.', '') }} {{ number_format(100 - $slice['percentage'], 3, '.', '') }}"
                                            stroke-dashoffset="{{ number_format(-$slice['offset'], 3, '.', '') }}"
                                            transform="rotate(-90 60 60)" tabindex="0" focusable="true"
                                            aria-label="{{ $slice['label'] }}: {{ number_format($slice['count']) }} reservation{{ (int) $slice['count'] === 1 ? '' : 's' }}, {{ number_format($slice['percentage'], 1) }} percent">
                                        <title>{{ $slice['label'] }} · {{ number_format($slice['count']) }} reservation{{ (int) $slice['count'] === 1 ? '' : 's' }} ({{ number_format($slice['percentage'], 1) }}%)</title>
                                    </circle>
                                @endif
                            @endforeach
                            <text class="admin-report-donut-total" x="60" y="58">{{ number_format($overview['total_reservations']) }}</text>
                            <text class="admin-report-donut-caption" x="60" y="72">reservation{{ $overview['total_reservations'] === 1 ? '' : 's' }}</text>
                        </svg>
                        <ul class="admin-report-legend" aria-label="Status legend">
                            @foreach ($statusSlices as $slice)
                                <li tabindex="0" title="{{ $slice['label'] }}: {{ number_format($slice['count']) }} reservation{{ (int) $slice['count'] === 1 ? '' : 's' }}, {{ number_format($slice['percentage'], 1) }} percent">
                                    <span class="admin-report-legend-dot" style="--legend-color: {{ $statusColors[$slice['status']] ?? '#89939f' }}"></span>
                                    <span>{{ $slice['label'] }}</span>
                                    <strong>{{ number_format($slice['count']) }}</strong>
                                    <small>{{ number_format($slice['percentage'], 1) }}%</small>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <p class="admin-report-footnote">Pending requests are included in reservation counts, but not confirmed revenue or utilization.</p>
                </article>
            </div>
        </section>

        <section class="admin-report-section" id="report-fleet" aria-labelledby="report-fleet-heading">
            <div class="admin-report-section-heading">
                <div>
                    <p class="admin-eyebrow">Vehicle performance</p>
                    <h2 id="report-fleet-heading">Fleet utilization &amp; popularity</h2>
                </div>
                <span>Clipped to overlapping report dates</span>
            </div>
            <div class="admin-report-chart-grid">
                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
                        <div>
                            <h3>Overall utilization</h3>
                            <p>Rented days divided by available fleet days</p>
                        </div>
                    </div>
                    <div class="admin-report-gauge-layout">
                        <svg class="admin-report-gauge" viewBox="0 0 220 132" role="img"
                             aria-label="Overall fleet utilization {{ number_format($fleetUtilization, 1) }} percent, {{ number_format($fleet['rented_days'], 2) }} rented of {{ number_format($fleet['available_days'], 2) }} available fleet days">
                            <path class="admin-report-gauge-track" d="M 24 112 A 86 86 0 0 1 196 112" pathLength="100"></path>
                            <path class="admin-report-gauge-value" d="M 24 112 A 86 86 0 0 1 196 112" pathLength="100"
                                  stroke-dasharray="100 100" stroke-dashoffset="{{ 100 - $fleetUtilization }}"
                                  style="--report-gauge-offset: {{ 100 - $fleetUtilization }}">
                                <title>{{ number_format($fleetUtilization, 1) }}% fleet utilization</title>
                            </path>
                            <text class="admin-report-gauge-number" x="110" y="94">{{ number_format($fleetUtilization, 1) }}%</text>
                            <text class="admin-report-gauge-caption" x="110" y="114">utilization</text>
                        </svg>
                        <div class="admin-report-gauge-stats">
                            <div><span>Rented days</span><strong>{{ number_format($fleet['rented_days'], 2) }}</strong></div>
                            <div><span>Available days</span><strong>{{ number_format($fleet['available_days'], 2) }}</strong></div>
                        </div>
                    </div>
                    <div class="admin-report-insights" aria-label="Fleet insights">
                        @if ($busiestVehicle)
                            <p><i class="bi bi-trophy" aria-hidden="true"></i><span>Busiest vehicle</span><strong>{{ $busiestVehicle->name }}</strong></p>
                        @endif
                        @if ($highestUtilizationVehicle && $highestUtilizationVehicle['utilization'] > 0)
                            <p><i class="bi bi-graph-up-arrow" aria-hidden="true"></i><span>Highest utilization</span><strong>{{ $highestUtilizationVehicle['name'] }} · {{ number_format($highestUtilizationVehicle['utilization'], 1) }}%</strong></p>
                        @endif
                    </div>
                </article>

                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-bar-chart-steps" aria-hidden="true"></i></span>
                        <div>
                            <h3>Days by vehicle</h3>
                            <p>Rented, maintenance, and idle days in range</p>
                        </div>
                    </div>
                    @if ($fleet['vehicles']->isNotEmpty())
                        <div class="admin-report-stacked-list" role="list" aria-label="Vehicle days breakdown">
                            @foreach ($fleet['vehicles'] as $vehicle)
                                @php
                                    $rentedWidth = min(100, ($vehicle['rented_days'] / $reportRangeDays) * 100);
                                    $maintenanceWidth = min(100 - $rentedWidth, ($vehicle['maintenance_days'] / $reportRangeDays) * 100);
                                    $idleWidth = min(100 - $rentedWidth - $maintenanceWidth, ($vehicle['idle_days'] / $reportRangeDays) * 100);
                                @endphp
                                <div class="admin-report-stacked-row" role="listitem">
                                    <div class="admin-report-stacked-label">
                                        <strong>{{ $vehicle['name'] }}</strong>
                                        <small>{{ ucfirst($vehicle['category']) }} · {{ number_format($vehicle['utilization'], 1) }}%</small>
                                    </div>
                                    <div class="admin-report-stacked-track" role="img"
                                         aria-label="{{ $vehicle['name'] }}: {{ number_format($vehicle['rented_days'], 2) }} rented days, {{ number_format($vehicle['maintenance_days'], 2) }} maintenance days, {{ number_format($vehicle['idle_days'], 2) }} idle days">
                                        <span class="admin-report-stacked-segment admin-report-stacked-segment--rented" style="width: {{ $rentedWidth }}%" title="Rented: {{ number_format($vehicle['rented_days'], 2) }} days" tabindex="0"></span>
                                        <span class="admin-report-stacked-segment admin-report-stacked-segment--maintenance" style="width: {{ $maintenanceWidth }}%" title="Maintenance: {{ number_format($vehicle['maintenance_days'], 2) }} days" tabindex="0"></span>
                                        <span class="admin-report-stacked-segment admin-report-stacked-segment--idle" style="width: {{ $idleWidth }}%" title="Idle: {{ number_format($vehicle['idle_days'], 2) }} days" tabindex="0"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="admin-report-bar-legend">
                            <span><i class="admin-report-legend-dot admin-report-legend-dot--rented"></i>Rented</span>
                            <span><i class="admin-report-legend-dot admin-report-legend-dot--maintenance"></i>Maintenance</span>
                            <span><i class="admin-report-legend-dot admin-report-legend-dot--idle"></i>Idle</span>
                        </div>
                    @else
                        <div class="admin-report-empty admin-report-empty--chart"><strong>No vehicles match these filters.</strong></div>
                    @endif
                </article>
            </div>

            <article class="admin-panel admin-report-panel admin-report-card">
                <div class="admin-report-card-heading">
                    <span class="admin-report-card-icon"><i class="bi bi-table" aria-hidden="true"></i></span>
                    <div>
                        <h3>Fleet detail</h3>
                        <p>Availability uses active vehicles and clipped rental intervals</p>
                    </div>
                </div>
                @if ($fleet['vehicles']->isNotEmpty())
                    <div class="admin-report-table-wrap">
                        <table class="admin-report-table admin-report-wide-table">
                            <thead>
                                <tr>
                                    <th scope="col">Vehicle</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Confirmed bookings</th>
                                    <th scope="col">Rented days</th>
                                    <th scope="col">Available days</th>
                                    <th scope="col">Utilization</th>
                                    <th scope="col">Maintenance days</th>
                                    <th scope="col">Idle days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fleet['vehicles'] as $vehicle)
                                    <tr>
                                        <td>{{ $vehicle['name'] }}</td>
                                        <td>{{ ucfirst($vehicle['category']) }}</td>
                                        <td>{{ number_format($vehicle['booking_count']) }}</td>
                                        <td>{{ number_format($vehicle['rented_days'], 2) }}</td>
                                        <td>{{ number_format($vehicle['available_days'], 2) }}</td>
                                        <td>{{ number_format($vehicle['utilization'], 1) }}%</td>
                                        <td>{{ number_format($vehicle['maintenance_days'], 2) }}</td>
                                        <td>{{ number_format($vehicle['idle_days'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="admin-report-footnote">
                    Available days include only vehicles currently marked Active, less the union of scheduled maintenance intervals.
                    Rented time uses approved, ongoing, and completed booking intervals; the exclusive return boundary is not counted.
                    Historical vehicle status changes are not stored. Utilization is capped at 100%.
                </p>
            </article>

            <div class="admin-report-chart-grid admin-report-chart-grid--popular">
                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-trophy" aria-hidden="true"></i></span>
                        <div>
                            <h3>Popular vehicles</h3>
                            <p>Ranked by confirmed bookings</p>
                        </div>
                    </div>
                    @if ($popular_vehicles->isNotEmpty())
                        <div class="admin-report-rank-list" role="img" aria-label="Popular vehicles ranked by confirmed bookings">
                            @foreach ($popular_vehicles as $vehicle)
                                <div class="admin-report-rank-row">
                                    <span class="admin-report-rank-badge">{{ $loop->iteration }}</span>
                                    <span class="admin-report-rank-name">{{ $vehicle->name }}<small>{{ ucfirst($vehicle->category) }} · {{ number_format($vehicle->rented_days) }} booked days</small></span>
                                    <span class="admin-report-rank-bar" title="{{ number_format($vehicle->booking_count) }} confirmed bookings" tabindex="0">
                                        <span style="width: {{ min(100, ($vehicle->booking_count / $maxPopularBookings) * 100) }}%"></span>
                                    </span>
                                    <strong>{{ number_format($vehicle->booking_count) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="admin-report-empty admin-report-empty--compact"><strong>No confirmed vehicle bookings in this range.</strong></div>
                    @endif
                </article>

                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-pie-chart-fill" aria-hidden="true"></i></span>
                        <div>
                            <h3>Popular categories</h3>
                            <p>Share of confirmed bookings</p>
                        </div>
                    </div>
                    @if ($categoryTotal > 0)
                        <div class="admin-report-donut-layout">
                            <svg class="admin-report-donut admin-report-donut--compact" viewBox="0 0 120 120" role="img"
                                 aria-label="Confirmed booking category share: {{ $categorySummary }}">
                                <circle class="admin-report-donut-track" cx="60" cy="60" r="42" pathLength="100"></circle>
                                @foreach ($categorySlices as $slice)
                                    <circle class="admin-report-donut-segment" cx="60" cy="60" r="42" pathLength="100"
                                            stroke="{{ $chartPalette[$loop->index % count($chartPalette)] }}"
                                            stroke-dasharray="{{ number_format($slice['percentage'], 3, '.', '') }} {{ number_format(100 - $slice['percentage'], 3, '.', '') }}"
                                            stroke-dashoffset="{{ number_format(-$slice['offset'], 3, '.', '') }}"
                                            transform="rotate(-90 60 60)" tabindex="0" focusable="true"
                                            aria-label="{{ $slice['label'] }}: {{ $slice['count'] }} booking{{ (int) $slice['count'] === 1 ? '' : 's' }}, {{ number_format($slice['percentage'], 1) }} percent">
                                        <title>{{ $slice['label'] }} · {{ $slice['count'] }} booking{{ (int) $slice['count'] === 1 ? '' : 's' }} ({{ number_format($slice['percentage'], 1) }}%)</title>
                                    </circle>
                                @endforeach
                                <text class="admin-report-donut-total" x="60" y="58">{{ number_format($categoryTotal) }}</text>
                                <text class="admin-report-donut-caption" x="60" y="72">bookings</text>
                            </svg>
                            <ul class="admin-report-legend" aria-label="Category booking share">
                                @foreach ($categorySlices as $slice)
                                    <li tabindex="0" title="{{ $slice['label'] }}: {{ $slice['count'] }} booking{{ (int) $slice['count'] === 1 ? '' : 's' }}, {{ number_format($slice['percentage'], 1) }} percent">
                                        <span class="admin-report-legend-dot" style="--legend-color: {{ $chartPalette[$loop->index % count($chartPalette)] }}"></span>
                                        <span>{{ $slice['label'] }}</span>
                                        <strong>{{ $slice['count'] }}</strong>
                                        <small>{{ number_format($slice['percentage'], 1) }}%</small>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="admin-report-empty admin-report-empty--compact"><strong>No confirmed category bookings in this range.</strong></div>
                    @endif
                </article>
            </div>
        </section>

        <section class="admin-report-section" id="report-customers" aria-labelledby="report-customers-heading">
            <div class="admin-report-section-heading">
                <div>
                    <p class="admin-eyebrow">Guest-style customer records</p>
                    <h2 id="report-customers-heading">Customer insights</h2>
                </div>
                <span>No customer accounts or registrations are implied</span>
            </div>
            <div class="admin-report-chart-grid">
                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                        <div>
                            <h3>New vs. returning</h3>
                            <p>Customer records with matching reservation activity</p>
                        </div>
                    </div>
                    <div class="admin-report-donut-layout">
                        <svg class="admin-report-donut admin-report-donut--compact" viewBox="0 0 120 120" role="img"
                             aria-label="Customer records: {{ number_format($customers['new_records']) }} new and {{ number_format($customers['returning_customers']) }} returning">
                            <circle class="admin-report-donut-track" cx="60" cy="60" r="42" pathLength="100"></circle>
                            @foreach ($customerSlices as $slice)
                                @if ($slice['percentage'] > 0)
                                    <circle class="admin-report-donut-segment" cx="60" cy="60" r="42" pathLength="100"
                                            stroke="{{ $slice['color'] }}"
                                            stroke-dasharray="{{ number_format($slice['percentage'], 3, '.', '') }} {{ number_format(100 - $slice['percentage'], 3, '.', '') }}"
                                            stroke-dashoffset="{{ number_format(-$slice['offset'], 3, '.', '') }}"
                                            transform="rotate(-90 60 60)" tabindex="0" focusable="true"
                                            aria-label="{{ $slice['label'] }}: {{ $slice['count'] }} records, {{ number_format($slice['percentage'], 1) }} percent">
                                        <title>{{ $slice['label'] }} · {{ $slice['count'] }} records ({{ number_format($slice['percentage'], 1) }}%)</title>
                                    </circle>
                                @endif
                            @endforeach
                            <text class="admin-report-donut-total" x="60" y="58">{{ number_format($customerTotal) }}</text>
                            <text class="admin-report-donut-caption" x="60" y="72">records</text>
                        </svg>
                        <ul class="admin-report-legend" aria-label="Customer record legend">
                            @foreach ($customerSlices as $slice)
                                <li tabindex="0" title="{{ $slice['label'] }}: {{ $slice['count'] }} records, {{ number_format($slice['percentage'], 1) }} percent">
                                    <span class="admin-report-legend-dot" style="--legend-color: {{ $slice['color'] }}"></span>
                                    <span>{{ $slice['label'] }}</span>
                                    <strong>{{ number_format($slice['count']) }}</strong>
                                    <small>{{ number_format($slice['percentage'], 1) }}%</small>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <p class="admin-report-footnote">New uses the customer record’s created date. Returning means the record existed before the selected range and has a matching booking. Pending requests are included in customer activity.</p>
                </article>
                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-award" aria-hidden="true"></i></span>
                        <div>
                            <h3>Top customers</h3>
                            <p>Completed booking history · no contact details</p>
                        </div>
                    </div>
                    @if ($customers['top_customers']->isNotEmpty())
                        <div class="admin-report-table-wrap">
                            <table class="admin-report-table">
                                <thead><tr><th scope="col">Rank</th><th scope="col">Customer</th><th scope="col">Completed bookings</th></tr></thead>
                                <tbody>
                                    @foreach ($customers['top_customers'] as $customer)
                                        <tr>
                                            <td><span class="admin-report-rank-badge">{{ $loop->iteration }}</span></td>
                                            <td>{{ $customer->full_name }}</td>
                                            <td>{{ number_format($customer->completed_bookings) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="admin-report-empty admin-report-empty--compact"><strong>No completed bookings in this range.</strong></div>
                    @endif
                    <p class="admin-report-footnote">Contact details are not shown or included in exports.</p>
                </article>
            </div>
        </section>

        <section class="admin-report-section" id="report-maintenance" aria-labelledby="report-maintenance-heading">
            <div class="admin-report-section-heading">
                <div>
                    <p class="admin-eyebrow">Service planning</p>
                    <h2 id="report-maintenance-heading">Maintenance summary</h2>
                </div>
                <span>Existing maintenance records only</span>
            </div>
            <div class="admin-report-summary-bar" aria-label="Maintenance summary">
                <article><span>Periods in range</span><strong>{{ number_format($maintenance['period_count']) }}</strong></article>
                <article><span>Maintenance days</span><strong>{{ number_format($maintenance['days'], 2) }}</strong></article>
                <article><span>Currently under maintenance</span><strong>{{ number_format($maintenance['vehicles_currently_under_maintenance']) }}</strong></article>
            </div>
            <div class="admin-report-chart-grid">
                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-wrench-adjustable" aria-hidden="true"></i></span>
                        <div>
                            <h3>Maintenance days by vehicle</h3>
                            <p>Union of intervals, clipped to the selected range</p>
                        </div>
                    </div>
                    @php
                        $maintenanceVehicles = $fleet['vehicles']->filter(fn (array $vehicle) => $vehicle['maintenance_days'] > 0);
                    @endphp
                    @if ($maintenanceVehicles->isNotEmpty())
                        <div class="admin-report-maintenance-bars" role="img" aria-label="Maintenance days per vehicle">
                            @foreach ($maintenanceVehicles as $vehicle)
                                <div class="admin-report-maintenance-row">
                                    <div class="admin-report-stacked-label">
                                        <strong>{{ $vehicle['name'] }}</strong>
                                        <small>{{ ucfirst($vehicle['category']) }}</small>
                                    </div>
                                    <div class="admin-report-maintenance-track">
                                        <span style="width: {{ min(100, ($vehicle['maintenance_days'] / $maxMaintenanceDays) * 100) }}%"
                                              title="{{ $vehicle['name'] }}: {{ number_format($vehicle['maintenance_days'], 2) }} maintenance days"
                                              tabindex="0"
                                              aria-label="{{ $vehicle['name'] }}: {{ number_format($vehicle['maintenance_days'], 2) }} maintenance days"></span>
                                    </div>
                                    <strong>{{ number_format($vehicle['maintenance_days'], 2) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="admin-report-empty admin-report-empty--compact"><strong>No maintenance days in this range.</strong></div>
                    @endif
                </article>

                <article class="admin-panel admin-report-panel admin-report-card">
                    <div class="admin-report-card-heading">
                        <span class="admin-report-card-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                        <div>
                            <h3>Scheduled periods</h3>
                            <p>Maintenance records overlapping the range</p>
                        </div>
                    </div>
                    @if ($maintenance['periods']->isNotEmpty())
                    <div class="admin-report-table-wrap">
                        <table class="admin-report-table">
                            <thead><tr><th scope="col">Vehicle</th><th scope="col">Category</th><th scope="col">Starts</th><th scope="col">Ends</th><th scope="col">Reason</th></tr></thead>
                            <tbody>
                                @foreach ($maintenance['periods'] as $period)
                                    <tr>
                                        <td>{{ $period->vehicle?->name ?? 'Vehicle record' }}</td>
                                        <td>{{ ucfirst($period->vehicle?->category ?? '') }}</td>
                                        <td>{{ $period->start_datetime->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                                        <td>{{ $period->end_datetime->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                                        <td>{{ $period->reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="admin-report-pagination">{{ $maintenance['periods']->links() }}</div>
                    @else
                        <div class="admin-report-empty admin-report-empty--compact"><strong>No maintenance periods overlap this range.</strong></div>
                    @endif
                    <p class="admin-report-footnote">Overlapping periods are counted once and clipped to the selected date range.</p>
                </article>
            </div>
        </section>

        <section class="admin-report-section" id="report-operations" aria-labelledby="report-operations-heading">
            <div class="admin-report-section-heading">
                <div>
                    <p class="admin-eyebrow">Rental lifecycle</p>
                    <h2 id="report-operations-heading">Rental operations</h2>
                </div>
                <span>Completed and overdue states</span>
            </div>
            <div class="admin-report-operations-grid">
                <article class="admin-panel admin-report-operation-card">
                    <span class="admin-report-operation-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
                    <span class="admin-report-operation-label">Completed rentals</span>
                    <strong>{{ number_format($operations['completed_rentals']) }}</strong>
                    <div class="admin-report-progress-track" role="img" aria-label="{{ number_format($operations['completed_rentals']) }} completed rentals among {{ number_format($overview['total_reservations']) }} reservations">
                        <span class="admin-report-progress-fill" style="width: {{ $completedWidth }}%"></span>
                    </div>
                    <small>Completed status, by pickup date</small>
                </article>
                <article class="admin-panel admin-report-operation-card">
                    <span class="admin-report-operation-icon admin-report-operation-icon--warning"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                    <span class="admin-report-operation-label">Overdue returns</span>
                    <strong>{{ number_format($operations['overdue_returns']) }}</strong>
                    <div class="admin-report-progress-track admin-report-progress-track--warning" role="img" aria-label="{{ number_format($operations['overdue_returns']) }} overdue returns among {{ number_format($ongoingCount) }} ongoing bookings">
                        <span class="admin-report-progress-fill" style="width: {{ $overdueWidth }}%"></span>
                    </div>
                    <small>Ongoing; scheduled return before now</small>
                </article>
                <article class="admin-panel admin-report-operation-card">
                    <span class="admin-report-operation-icon admin-report-operation-icon--slate"><i class="bi bi-stopwatch" aria-hidden="true"></i></span>
                    <span class="admin-report-operation-label">Average actual rental length</span>
                    <strong>{{ $operations['average_actual_rental_days'] === null ? 'Not available' : number_format($operations['average_actual_rental_days'], 2).' days' }}</strong>
                    <div class="admin-report-progress-track admin-report-progress-track--slate" role="img" aria-label="{{ number_format($operations['paired_audit_rentals']) }} of {{ number_format($operations['completed_rentals']) }} completed rentals have paired check-out and check-in timestamps">
                        <span class="admin-report-progress-fill" style="width: {{ $auditCoverageWidth }}%"></span>
                    </div>
                    <small>{{ number_format($operations['paired_audit_rentals']) }} completed rentals with paired timestamps</small>
                </article>
            </div>
            <p class="admin-report-footnote">Actual rental length prefers the recorded check-out/check-in timestamps and falls back to legacy audit events. Completed rentals without both timestamps are excluded.</p>
        </section>

        <footer class="admin-page-footer">FleetGuard Reports · Read-only data · Generated {{ $now->timezone('Asia/Manila')->format('M j, Y g:i A') }} Manila time</footer>
    </main>
    <script>
        (() => {
            const navigation = document.querySelector('[data-report-section-nav]');
            const links = navigation ? Array.from(navigation.querySelectorAll('a[href^="#"]')) : [];
            const sections = links
                .map((link) => document.querySelector(link.getAttribute('href')))
                .filter(Boolean);

            if (!navigation || !('IntersectionObserver' in window)) {
                return;
            }

            const setActiveSection = (sectionId) => {
                links.forEach((link) => {
                    if (link.hash === `#${sectionId}`) {
                        link.setAttribute('aria-current', 'location');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
            };

            links.forEach((link) => link.addEventListener('click', () => setActiveSection(link.hash.slice(1))));

            const observer = new IntersectionObserver((entries) => {
                const visibleSection = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((left, right) => left.boundingClientRect.top - right.boundingClientRect.top)[0];

                if (visibleSection) {
                    setActiveSection(visibleSection.target.id);
                }
            }, { rootMargin: '-15% 0px -70% 0px', threshold: [0, 0.2, 0.5] });

            sections.forEach((section) => observer.observe(section));
        })();
    </script>
@endsection
