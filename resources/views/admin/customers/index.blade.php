@extends('layouts.admin')

@section('title', 'Customer Management | FleetGuard')
@section('admin-page-title', 'Customer Management')
@section('admin-page-icon', 'people')
@section('admin-breadcrumb', 'Customers')
@section('admin-page-description', 'Review customer records and their rental activity.')

@section('admin-content')
    @php
        $customerMetrics = [
            ['key' => 'total', 'label' => 'Total customers', 'description' => 'All customer records', 'icon' => 'people', 'query' => []],
            ['key' => 'pending', 'label' => 'With pending reservations', 'description' => 'Awaiting review', 'icon' => 'hourglass-split', 'query' => ['activity' => 'pending']],
            ['key' => 'upcoming', 'label' => 'With approved / upcoming bookings', 'description' => 'Confirmed rentals', 'icon' => 'calendar-check', 'query' => ['activity' => 'upcoming']],
            ['key' => 'ongoing', 'label' => 'With ongoing rentals', 'description' => 'Currently renting', 'icon' => 'key', 'query' => ['activity' => 'ongoing']],
            ['key' => 'completed', 'label' => 'With completed rental history', 'description' => 'At least one completed rental', 'icon' => 'check2-circle', 'query' => ['activity' => 'completed']],
            ['key' => 'blocked', 'label' => 'Blocked customers', 'description' => 'Cannot submit new bookings', 'icon' => 'slash-circle', 'query' => ['status' => 'blocked']],
            ['key' => 'new_this_month', 'label' => 'New customers', 'description' => 'Records created this month', 'icon' => 'person-plus', 'query' => ['joined' => 'month']],
        ];
        $activityLabels = [
            'none' => 'No bookings',
            'pending' => 'Pending reservations',
            'upcoming' => 'Approved / upcoming',
            'ongoing' => 'Ongoing rentals',
            'completed' => 'Completed history',
        ];
        $filterLabels = [
            'search' => isset($filters['search']) && $filters['search'] !== '' ? 'Search: '.$filters['search'] : null,
            'activity' => isset($filters['activity']) ? 'Activity: '.($activityLabels[$filters['activity']] ?? ucfirst($filters['activity'])) : null,
            'status' => isset($filters['status']) ? 'Status: '.ucfirst($filters['status']) : null,
            'joined' => ($filters['joined'] ?? null) === 'month' ? 'Created: This month' : null,
            'sort' => isset($filters['sort']) && $filters['sort'] !== 'created_at' ? 'Sort: '.ucfirst(str_replace('_', ' ', $filters['sort'])) : null,
            'direction' => isset($filters['direction']) && $filters['direction'] !== 'desc' ? 'Order: '.($filters['direction'] === 'asc' ? 'Oldest / A–Z' : 'Newest / Z–A') : null,
        ];
    @endphp

    <main class="admin-customers" id="main-content">
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

        <div class="admin-customers-overview-header">
            <p class="admin-customers-breadcrumb"><span>Admin</span><i class="bi bi-chevron-right" aria-hidden="true"></i>Customers</p>
            <span class="admin-customers-updated"><i class="bi bi-clock" aria-hidden="true"></i>Updated {{ $now->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</span>
        </div>

        <section class="admin-customer-metrics" aria-label="Customer statistics">
            @foreach ($customerMetrics as $metric)
                <a class="admin-customer-metric admin-section-card" href="{{ route('admin.customers.index', $metric['query']) }}" aria-label="Filter customers: {{ strtolower($metric['label']) }}">
                    <span class="admin-customer-metric-heading">
                        <span>{{ $metric['label'] }}</span>
                        <i class="bi bi-{{ $metric['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <strong>{{ number_format((int) $metrics[$metric['key']]) }}</strong>
                    <small>{{ $metric['description'] }}</small>
                </a>
            @endforeach
        </section>

        <section class="admin-customer-summary admin-section-card" aria-label="Booking activity summary">
            <div>
                <span class="admin-customer-summary-label">Booking records</span>
                <strong>{{ number_format(array_sum($metrics['booking_summary'])) }}</strong>
            </div>
            <ul>
                <li><span class="admin-customer-summary-dot admin-customer-summary-dot--pending" aria-hidden="true"></span>Pending <strong>{{ number_format($metrics['booking_summary']['pending']) }}</strong></li>
                <li><span class="admin-customer-summary-dot admin-customer-summary-dot--approved" aria-hidden="true"></span>Approved <strong>{{ number_format($metrics['booking_summary']['approved']) }}</strong></li>
                <li><span class="admin-customer-summary-dot admin-customer-summary-dot--ongoing" aria-hidden="true"></span>Ongoing <strong>{{ number_format($metrics['booking_summary']['ongoing']) }}</strong></li>
                <li><span class="admin-customer-summary-dot admin-customer-summary-dot--completed" aria-hidden="true"></span>Completed <strong>{{ number_format($metrics['booking_summary']['completed']) }}</strong></li>
                <li><span class="admin-customer-summary-dot admin-customer-summary-dot--rejected" aria-hidden="true"></span>Rejected <strong>{{ number_format($metrics['booking_summary']['rejected']) }}</strong></li>
                <li><span class="admin-customer-summary-dot admin-customer-summary-dot--cancelled" aria-hidden="true"></span>Cancelled <strong>{{ number_format($metrics['booking_summary']['cancelled']) }}</strong></li>
                <li><span class="admin-customer-summary-dot admin-customer-summary-dot--none" aria-hidden="true"></span>No bookings <strong>{{ number_format($metrics['without_bookings']) }}</strong></li>
            </ul>
        </section>

        <section class="admin-customers-panel admin-section-card" aria-labelledby="customer-directory-heading">
            <div class="admin-customers-panel-heading">
                <div>
                    <p class="admin-customers-eyebrow">Customer records</p>
                    <h2 id="customer-directory-heading">Customer directory</h2>
                </div>
                <div class="admin-customers-panel-actions">
                    <span class="admin-customers-result-count">
                        Showing {{ number_format($customers->firstItem() ?? 0) }}–{{ number_format($customers->lastItem() ?? 0) }} of {{ number_format($customers->total()) }}
                    </span>
                    <div class="admin-customers-view-toggle" role="group" aria-label="Customer directory view">
                        <button type="button" data-customer-view-toggle="table" aria-pressed="true" aria-label="Table view" title="Table view"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                        <button type="button" data-customer-view-toggle="grid" aria-pressed="false" aria-label="Card view" title="Card view"><i class="bi bi-grid" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>

            <form class="admin-customers-filters" method="GET" action="{{ route('admin.customers.index') }}" data-preserve-search-position>
                <label class="admin-customers-search">
                    <span>Search customers</span>
                    <span class="admin-customers-input-wrap">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            maxlength="100"
                            placeholder="Name, email, or phone"
                            aria-label="Search customers by name, email, or phone number"
                        >
                    </span>
                    <small class="admin-search-hint">Press Enter to search</small>
                </label>
                <label>
                    <span>Booking activity</span>
                    <select name="activity" aria-label="Filter by booking activity" data-admin-submit-on-change>
                        <option value="">All activity</option>
                        @foreach ($activityLabels as $activity => $label)
                            <option value="{{ $activity }}" @selected(($filters['activity'] ?? '') === $activity)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Customer status</span>
                    <select name="status" aria-label="Filter by customer status" data-admin-submit-on-change>
                        <option value="">All statuses</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="blocked" @selected(($filters['status'] ?? '') === 'blocked')>Blocked</option>
                    </select>
                </label>
                <label>
                    <span>Record date</span>
                    <select name="joined" aria-label="Filter by record creation date" data-admin-submit-on-change>
                        <option value="">Any time</option>
                        <option value="month" @selected(($filters['joined'] ?? '') === 'month')>Created this month</option>
                    </select>
                </label>
                <label>
                    <span>Sort by</span>
                    <select name="sort" aria-label="Sort customers" data-admin-submit-on-change>
                        @foreach ([
                            'created_at' => 'Recently created',
                            'full_name' => 'Customer name',
                            'email' => 'Email',
                            'total_bookings' => 'Booking count',
                            'latest_booking_at' => 'Latest booking',
                        ] as $sortKey => $sortLabel)
                            <option value="{{ $sortKey }}" @selected(($filters['sort'] ?? 'created_at') === $sortKey)>{{ $sortLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-customers-direction">
                    <span>Order</span>
                    <select name="direction" aria-label="Sort direction" data-admin-submit-on-change>
                        <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Descending</option>
                        <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Ascending</option>
                    </select>
                </label>
                <a class="admin-customers-clear" href="{{ route('admin.customers.index') }}">Clear filters</a>
            </form>

            @if (collect($filterLabels)->filter(fn ($label) => $label !== null && $label !== '')->isNotEmpty())
                <div class="admin-customers-filter-chips" aria-label="Active filters">
                    @foreach ($filterLabels as $filterKey => $filterLabel)
                        @if ($filterLabel)
                            @php
                                $remainingFilters = request()->query();
                                unset($remainingFilters[$filterKey], $remainingFilters['page']);
                                $remainingFilters = array_filter($remainingFilters, fn ($value) => $value !== '' && $value !== null);
                            @endphp
                            <a class="admin-customers-filter-chip" href="{{ route('admin.customers.index', $remainingFilters) }}">
                                <span>{{ $filterLabel }}</span>
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                <span class="sr-only">Remove filter</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="admin-customers-table-wrap" data-customer-view-panel="table">
                <table class="admin-customers-table">
                    <thead>
                        <tr>
                            <th scope="col">Customer</th>
                            <th scope="col">Status</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Bookings</th>
                            <th scope="col">Latest booking</th>
                            <th scope="col">Record created</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td data-label="Customer">
                                    <a class="admin-customer-identity" href="{{ route('admin.customers.show', $customer) }}">
                                        <span class="admin-customer-avatar" aria-hidden="true">{{ $customer->initials() }}</span>
                                        <span><strong>{{ $customer->full_name }}</strong><small>Customer #{{ $customer->id }}</small></span>
                                    </a>
                                </td>
                                <td data-label="Status">
                                    <span class="admin-customer-status admin-customer-status--{{ $customer->status }}">
                                        <i class="bi bi-{{ $customer->status === \App\Models\Customer::STATUS_BLOCKED ? 'slash-circle' : 'check-circle' }}" aria-hidden="true"></i>
                                        {{ ucfirst($customer->status) }}
                                    </span>
                                </td>
                                <td data-label="Email">{{ $customer->display_email }}</td>
                                <td data-label="Phone">{{ $customer->display_phone }}</td>
                                <td data-label="Bookings"><span class="admin-customer-booking-count">{{ number_format($customer->total_bookings) }}</span></td>
                                <td data-label="Latest booking">
                                    @if ($customer->latest_booking_at)
                                        <span class="admin-customer-latest-date">{{ \Illuminate\Support\Carbon::parse($customer->latest_booking_at)->timezone('Asia/Manila')->format('M d, Y') }}</span>
                                        <span class="admin-customer-status admin-customer-status--{{ $customer->latest_booking_status }}">{{ ucfirst($customer->latest_booking_status) }}</span>
                                    @else
                                        <span class="admin-customer-muted">No booking history</span>
                                    @endif
                                </td>
                                <td data-label="Record created">{{ $customer->created_at?->timezone('Asia/Manila')->format('M d, Y') }}</td>
                                <td data-label="Actions">
                                    <span class="admin-customer-row-actions">
                                        <a class="admin-customer-action-link" href="{{ route('admin.customers.show', $customer) }}" aria-label="View {{ $customer->full_name }} customer record">
                                            <i class="bi bi-eye" aria-hidden="true"></i><span>Details</span>
                                        </a>
                                        @if ($canEdit)
                                            <a class="admin-customer-action-link" href="{{ route('admin.customers.edit', $customer) }}" aria-label="Edit {{ $customer->full_name }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span>
                                            </a>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="admin-customers-empty" colspan="8">
                                    <i class="bi bi-people" aria-hidden="true"></i>
                                    <strong>{{ request()->filled('search') ? 'No matching customers' : 'No customer records found' }}</strong>
                                    <span>{{ request()->filled('search') || $filters !== [] ? 'Clear filters or try a different search.' : 'Customer records will appear here after a booking is submitted.' }}</span>
                                    @if (request()->query() !== [])
                                        <a class="admin-customer-secondary-link" href="{{ route('admin.customers.index') }}">Clear filters</a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-customers-grid" data-customer-view-panel="grid" hidden>
                @forelse ($customers as $customer)
                    @php
                        $customerCardDate = $customer->latest_booking_at
                            ? \Illuminate\Support\Carbon::parse($customer->latest_booking_at)->timezone('Asia/Manila')->format('M d, Y')
                            : null;
                    @endphp
                    <article class="admin-customer-card">
                        <div class="admin-customer-card-heading">
                            <span class="admin-customer-avatar" aria-hidden="true">{{ $customer->initials() }}</span>
                            <div><h3>{{ $customer->full_name }}</h3><span>Customer #{{ $customer->id }}</span></div>
                            <strong class="admin-customer-booking-count">{{ number_format($customer->total_bookings) }} <small>bookings</small></strong>
                        </div>
                        <dl>
                            <div><dt>Status</dt><dd><span class="admin-customer-status admin-customer-status--{{ $customer->status }}">{{ ucfirst($customer->status) }}</span></dd></div>
                            <div><dt>Email</dt><dd>{{ $customer->display_email }}</dd></div>
                            <div><dt>Phone</dt><dd>{{ $customer->display_phone }}</dd></div>
                            <div>
                                <dt>Latest booking</dt>
                                <dd>
                                    @if ($customerCardDate)
                                        {{ $customerCardDate }} · {{ ucfirst($customer->latest_booking_status) }}
                                    @else
                                        No booking history
                                    @endif
                                </dd>
                            </div>
                            <div><dt>Record created</dt><dd>{{ $customer->created_at?->timezone('Asia/Manila')->format('M d, Y') }}</dd></div>
                        </dl>
                        <footer>
                            <a class="admin-customer-secondary-link" href="{{ route('admin.customers.show', $customer) }}"><i class="bi bi-eye" aria-hidden="true"></i><span>View details</span></a>
                            @if ($canEdit)
                                <a class="admin-customer-primary-link" href="{{ route('admin.customers.edit', $customer) }}"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></a>
                            @endif
                        </footer>
                    </article>
                @empty
                    <div class="admin-customers-grid-empty">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <strong>No matching customers</strong>
                        <span>Clear filters or try another search.</span>
                        <a class="admin-customer-secondary-link" href="{{ route('admin.customers.index') }}">Clear filters</a>
                    </div>
                @endforelse
            </div>

            @if ($customers->hasPages())
                <div class="admin-customers-pagination">{{ $customers->links() }}</div>
            @endif
        </section>
    </main>
@endsection
