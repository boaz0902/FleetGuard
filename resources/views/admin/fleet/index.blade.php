@extends('layouts.admin')

@section('title', 'Fleet Management | FleetGuard')
@section('admin-page-title', 'Fleet Management')
@section('admin-page-icon', 'car-front')
@section('admin-breadcrumb', 'Fleet')
@section('admin-page-description', 'Manage vehicle inventory, availability, and maintenance schedules.')

@section('admin-content')
    @php
        $fleetMetrics = [
            ['key' => 'total', 'label' => 'Total vehicles', 'description' => 'Registered inventory', 'icon' => 'car-front', 'query' => []],
            ['key' => 'active', 'label' => 'Active', 'description' => 'Enabled for rentals', 'icon' => 'check-circle', 'query' => ['status' => 'active']],
            ['key' => 'available', 'label' => 'Available', 'description' => 'Available right now', 'icon' => 'calendar-check', 'query' => ['search' => 'available']],
            ['key' => 'reserved', 'label' => 'Reserved', 'description' => 'Approved bookings now', 'icon' => 'calendar2-check', 'query' => ['search' => 'reserved']],
            ['key' => 'ongoing', 'label' => 'Ongoing rentals', 'description' => 'Currently rented', 'icon' => 'key', 'query' => ['search' => 'ongoing']],
            ['key' => 'maintenance', 'label' => 'Under maintenance', 'description' => 'Unavailable for service', 'icon' => 'tools', 'query' => ['search' => 'maintenance']],
            ['key' => 'inactive', 'label' => 'Inactive', 'description' => 'Not enabled for rentals', 'icon' => 'slash-circle', 'query' => ['status' => 'inactive']],
        ];
        $fleetFilterLabels = [
            'search' => isset($filters['search']) && $filters['search'] !== '' ? 'Search: '.$filters['search'] : null,
            'category' => isset($filters['category']) ? 'Category: '.ucfirst($filters['category']) : null,
            'status' => isset($filters['status']) ? 'Status: '.ucfirst($filters['status']) : null,
            'sort' => isset($filters['sort']) && $filters['sort'] !== 'created_at' ? 'Sort: '.ucfirst(str_replace('_', ' ', $filters['sort'])) : null,
            'direction' => isset($filters['direction']) && $filters['direction'] !== 'desc' ? 'Order: '.($filters['direction'] === 'asc' ? 'Oldest / A–Z' : 'Newest / Z–A') : null,
        ];
        $fleetHealthSegments = [
            ['key' => 'available', 'label' => 'Available', 'class' => 'available'],
            ['key' => 'reserved', 'label' => 'Reserved', 'class' => 'reserved'],
            ['key' => 'ongoing', 'label' => 'Ongoing', 'class' => 'ongoing'],
            ['key' => 'maintenance', 'label' => 'Maintenance', 'class' => 'maintenance'],
            ['key' => 'inactive', 'label' => 'Inactive', 'class' => 'inactive'],
        ];
    @endphp

    <main class="admin-fleet" id="main-content">
        @if (session('status'))
            <div class="admin-fleet-flash" role="status">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="admin-fleet-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="admin-fleet-overview-header">
            <p class="admin-fleet-breadcrumb"><span>Admin</span><i class="bi bi-chevron-right" aria-hidden="true"></i>Fleet</p>
            <div class="admin-fleet-overview-actions">
                <span class="admin-fleet-last-updated"><i class="bi bi-clock" aria-hidden="true"></i>Last updated {{ $now->timezone('Asia/Manila')->format('M d, Y · g:i A') }}</span>
                @can('create', \App\Models\Vehicle::class)
                    <a class="admin-fleet-primary-link" href="{{ route('admin.fleet.create') }}">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span>Add vehicle</span>
                    </a>
                @endcan
            </div>
        </div>

        <section class="admin-fleet-metrics" aria-label="Fleet statistics">
            @foreach ($fleetMetrics as $metric)
                <a class="admin-fleet-metric admin-section-card" href="{{ route('admin.fleet.index', $metric['query']) }}" aria-label="Filter fleet by {{ strtolower($metric['label']) }}">
                    <div class="admin-fleet-metric-heading">
                        <span>{{ $metric['label'] }}</span>
                        <i class="bi bi-{{ $metric['icon'] }}" aria-hidden="true"></i>
                    </div>
                    <strong>{{ number_format((int) $fleetStatus[$metric['key']]) }}</strong>
                    <small>{{ $metric['description'] }}</small>
                </a>
            @endforeach
        </section>

        <section class="admin-fleet-health admin-section-card" aria-labelledby="fleet-health-heading">
            <div class="admin-fleet-health-heading">
                <div>
                    <p class="admin-fleet-eyebrow">Live fleet status</p>
                    <h2 id="fleet-health-heading">Fleet health</h2>
                </div>
                <span>{{ number_format((int) $fleetStatus['total']) }} vehicles</span>
            </div>
            <div
                class="admin-fleet-health-bar"
                role="img"
                aria-label="Fleet health: {{ collect($fleetHealthSegments)->map(fn ($segment) => number_format((int) $fleetStatus[$segment['key']]).' '.$segment['label'])->implode(', ') }}"
            >
                @foreach ($fleetHealthSegments as $segment)
                    @php
                        $segmentCount = (int) $fleetStatus[$segment['key']];
                        $segmentWidth = $fleetStatus['total'] > 0 ? ($segmentCount / $fleetStatus['total']) * 100 : 0;
                    @endphp
                    @if ($segmentCount > 0)
                        <span class="admin-fleet-health-segment admin-fleet-health-segment--{{ $segment['class'] }}" style="width: {{ $segmentWidth }}%"></span>
                    @endif
                @endforeach
            </div>
            <ul class="admin-fleet-health-legend">
                @foreach ($fleetHealthSegments as $segment)
                    <li><span class="admin-fleet-health-dot admin-fleet-health-dot--{{ $segment['class'] }}" aria-hidden="true"></span>{{ $segment['label'] }} <strong>{{ number_format((int) $fleetStatus[$segment['key']]) }}</strong></li>
                @endforeach
            </ul>
        </section>

        <section class="admin-fleet-panel admin-section-card" aria-labelledby="fleet-inventory-heading">
            <div class="admin-fleet-panel-heading">
                <div>
                    <p class="admin-fleet-eyebrow">Inventory register</p>
                    <h2 id="fleet-inventory-heading">Vehicle inventory</h2>
                </div>
                <div class="admin-fleet-panel-actions">
                    <span class="admin-fleet-result-count">
                        Showing {{ number_format($vehicles->firstItem() ?? 0) }}–{{ number_format($vehicles->lastItem() ?? 0) }} of {{ number_format($vehicles->total()) }} vehicles
                    </span>
                    <div class="admin-fleet-view-toggle" role="group" aria-label="Inventory view">
                        <button type="button" data-fleet-view-toggle="table" aria-pressed="true" aria-label="Table view" title="Table view"><i class="bi bi-list-ul" aria-hidden="true"></i></button>
                        <button type="button" data-fleet-view-toggle="grid" aria-pressed="false" aria-label="Grid view" title="Grid view"><i class="bi bi-grid" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>

            <form class="admin-fleet-filters" method="GET" action="{{ route('admin.fleet.index') }}" data-preserve-search-position>
                <label class="admin-fleet-search">
                    <span>Search fleet</span>
                    <span class="admin-fleet-input-wrap">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            maxlength="100"
                            placeholder="Vehicle, make/model, category, availability, rate, or date"
                            aria-label="Search vehicles by name, brand, model, category, status, availability, daily rate, or date added"
                        >
                    </span>
                    <small class="admin-search-hint">Press Enter to search</small>
                </label>
                <label>
                    <span>Category</span>
                    <select name="category" aria-label="Filter by category" data-admin-submit-on-change>
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Status</span>
                    <select name="status" aria-label="Filter by vehicle status" data-admin-submit-on-change>
                        <option value="">All statuses</option>
                        @foreach ([
                            \App\Models\Vehicle::STATUS_ACTIVE,
                            \App\Models\Vehicle::STATUS_INACTIVE,
                            \App\Models\Vehicle::STATUS_MAINTENANCE,
                        ] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Sort by</span>
                    <select name="sort" aria-label="Sort fleet vehicles" data-admin-submit-on-change>
                        @foreach (['created_at' => 'Recently added', 'name' => 'Vehicle name', 'category' => 'Category', 'daily_rate' => 'Daily rate', 'status' => 'Status'] as $sortKey => $sortLabel)
                            <option value="{{ $sortKey }}" @selected(($filters['sort'] ?? 'created_at') === $sortKey)>{{ $sortLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-fleet-direction">
                    <span>Order</span>
                    <select name="direction" aria-label="Sort direction" data-admin-submit-on-change>
                        <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Descending</option>
                        <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Ascending</option>
                    </select>
                </label>
                <a class="admin-fleet-clear-filters" href="{{ route('admin.fleet.index') }}">Clear filters</a>
            </form>

            @if (collect($fleetFilterLabels)->filter(fn ($label) => $label !== null && $label !== '')->isNotEmpty())
                <div class="admin-fleet-filter-chips" aria-label="Active filters">
                    @foreach ($fleetFilterLabels as $filterKey => $filterLabel)
                        @if ($filterLabel)
                            @php
                                $remainingFilters = request()->query();
                                unset($remainingFilters[$filterKey], $remainingFilters['page']);
                                $remainingFilters = array_filter($remainingFilters, fn ($value) => $value !== '' && $value !== null);
                            @endphp
                            <a class="admin-fleet-filter-chip" href="{{ route('admin.fleet.index', $remainingFilters) }}">
                                <span>{{ $filterLabel }}</span>
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                <span class="sr-only">Remove filter</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="admin-fleet-table-wrap" data-fleet-view-panel="table">
                    <table class="admin-fleet-table">
                        <thead>
                            <tr>
                                <th scope="col">Vehicle</th>
                                <th scope="col">Category</th>
                                <th scope="col">Vehicle status</th>
                                <th scope="col">Current availability</th>
                                <th scope="col">Daily rate</th>
                                <th scope="col">Date added</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vehicles as $vehicle)
                                @php $availability = $availabilityStatuses[$vehicle->id] ?? $vehicle->status; @endphp
                                <tr>
                                    <td data-label="Vehicle">
                                        <span class="admin-fleet-vehicle">
                                            <span class="admin-fleet-vehicle-image">
                                                @if ($vehicle->image_path)
                                                    <img src="{{ asset($vehicle->image_path) }}" alt="" loading="lazy">
                                                @else
                                                    <i class="bi bi-car-front" aria-hidden="true"></i>
                                                @endif
                                            </span>
                                            <span>
                                                <strong>{{ $vehicle->name }}</strong>
                                                <small>{{ $vehicle->brand }} {{ $vehicle->model }}</small>
                                            </span>
                                        </span>
                                    </td>
                                    <td data-label="Category"><span class="admin-fleet-category-pill">{{ ucfirst($vehicle->category) }}</span></td>
                                    <td data-label="Vehicle status">
                                        <span class="admin-fleet-status admin-fleet-status--{{ $vehicle->status }}">
                                            <i class="bi bi-circle-fill" aria-hidden="true"></i>
                                            {{ ucfirst($vehicle->status) }}
                                        </span>
                                    </td>
                                    <td data-label="Current availability">
                                        <span class="admin-fleet-availability admin-fleet-availability--{{ $availability }}">
                                            {{ ucfirst($availability) }}
                                        </span>
                                    </td>
                                    <td data-label="Daily rate" class="admin-fleet-rate">₱{{ number_format((float) $vehicle->daily_rate, 2) }}</td>
                                    <td data-label="Date added">{{ $vehicle->created_at?->timezone('Asia/Manila')->format('M d, Y') }}</td>
                                    <td data-label="Actions">
                                        <span class="admin-fleet-row-actions">
                                            <a class="admin-fleet-action-link" href="{{ route('admin.fleet.show', $vehicle) }}" aria-label="View {{ $vehicle->name }} details" title="View details">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                                <span>Details</span>
                                            </a>
                                            @can('update', $vehicle)
                                                <a class="admin-fleet-action-link" href="{{ route('admin.fleet.edit', $vehicle) }}" aria-label="Edit {{ $vehicle->name }}" title="Edit vehicle">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                    <span>Edit</span>
                                                </a>
                                            @endcan
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr class="admin-fleet-empty-row">
                                    <td class="admin-fleet-empty" colspan="7">
                                        <i class="bi bi-car-front" aria-hidden="true"></i>
                                        <strong>{{ request()->filled('search') ? 'No matching vehicles' : 'No vehicles in the fleet yet' }}</strong>
                                        <span>{{ collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty() ? 'Clear filters or try a different search.' : 'Add a vehicle to begin managing your fleet.' }}</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <div class="admin-fleet-grid" data-fleet-view-panel="grid" hidden>
                @forelse ($vehicles as $vehicle)
                    @php $availability = $availabilityStatuses[$vehicle->id] ?? $vehicle->status; @endphp
                    <article class="admin-fleet-vehicle-card">
                        <a class="admin-fleet-grid-image" href="{{ route('admin.fleet.show', $vehicle) }}" aria-label="View {{ $vehicle->name }} details">
                            @if ($vehicle->image_path)
                                <img src="{{ asset($vehicle->image_path) }}" alt="" loading="lazy">
                            @else
                                <i class="bi bi-car-front" aria-hidden="true"></i>
                            @endif
                        </a>
                        <div class="admin-fleet-grid-card-body">
                            <div class="admin-fleet-grid-title">
                                <div>
                                    <h3>{{ $vehicle->name }}</h3>
                                    <p>{{ $vehicle->brand }} {{ $vehicle->model }}</p>
                                </div>
                                <span class="admin-fleet-category-pill">{{ ucfirst($vehicle->category) }}</span>
                            </div>
                            <strong class="admin-fleet-grid-rate">₱{{ number_format((float) $vehicle->daily_rate, 2) }} <small>/ day</small></strong>
                            <div class="admin-fleet-grid-badges">
                                <span class="admin-fleet-status admin-fleet-status--{{ $vehicle->status }}"><i class="bi bi-circle-fill" aria-hidden="true"></i>{{ ucfirst($vehicle->status) }}</span>
                                <span class="admin-fleet-availability admin-fleet-availability--{{ $availability }}">{{ ucfirst($availability) }}</span>
                            </div>
                        </div>
                        <footer class="admin-fleet-grid-actions">
                            <a class="admin-fleet-secondary-link" href="{{ route('admin.fleet.show', $vehicle) }}"><i class="bi bi-eye" aria-hidden="true"></i><span>View</span></a>
                            @can('update', $vehicle)
                                <a class="admin-fleet-primary-link" href="{{ route('admin.fleet.edit', $vehicle) }}"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit</span></a>
                            @endcan
                        </footer>
                    </article>
                @empty
                    <div class="admin-fleet-grid-empty">
                        <i class="bi bi-car-front" aria-hidden="true"></i>
                        <strong>No matching vehicles</strong>
                        <span>Clear filters or try a different search.</span>
                        <a class="admin-fleet-secondary-link" href="{{ route('admin.fleet.index') }}">Clear filters</a>
                    </div>
                @endforelse
            </div>

            @if ($vehicles->hasPages())
                <div class="admin-fleet-pagination">{{ $vehicles->links() }}</div>
            @endif
        </section>

        <section class="admin-fleet-maintenance admin-section-card" aria-labelledby="fleet-maintenance-heading">
            <div class="admin-fleet-panel-heading">
                <div>
                    <p class="admin-fleet-eyebrow">Service schedule</p>
                    <h2 id="fleet-maintenance-heading">Upcoming maintenance</h2>
                </div>
                <span class="admin-fleet-result-count">{{ number_format($maintenanceRecords->total()) }} records</span>
            </div>

            @if ($maintenanceRecords->isEmpty())
                <div class="admin-fleet-maintenance-empty">
                    <i class="bi bi-tools" aria-hidden="true"></i>
                    <strong>No upcoming maintenance</strong>
                    <span>Scheduled service windows will appear here.</span>
                </div>
            @else
                <div class="admin-fleet-maintenance-list">
                    @foreach ($maintenanceRecords as $record)
                        <article class="admin-fleet-maintenance-item">
                            <span class="admin-fleet-maintenance-icon"><i class="bi bi-tools" aria-hidden="true"></i></span>
                            <div class="admin-fleet-maintenance-info">
                                <strong>{{ $record->vehicle?->name ?? 'Vehicle' }}</strong>
                                <span>{{ $record->reason }}</span>
                                <small>
                                    {{ $record->start_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    {{ $record->end_datetime?->timezone('Asia/Manila')->format('M d, Y · g:i A') }}
                                </small>
                            </div>
                            @can('manage', \App\Models\Vehicle::class)
                                <form method="POST" action="{{ route('admin.fleet.maintenance.destroy', $record) }}" onsubmit="return confirm('Remove this maintenance period? The removal will be recorded in the audit history.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="admin-fleet-remove-maintenance" type="submit">
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                        <span>Remove</span>
                                    </button>
                                </form>
                            @endcan
                        </article>
                    @endforeach
                </div>
                @if ($maintenanceRecords->hasPages())
                    <div class="admin-fleet-pagination">{{ $maintenanceRecords->links() }}</div>
                @endif
            @endif

            @can('manage', \App\Models\Vehicle::class)
                <form class="admin-fleet-maintenance-form" method="POST" action="{{ route('admin.fleet.maintenance.store') }}">
                    @csrf
                    <div class="admin-fleet-maintenance-form-heading">
                        <span class="admin-fleet-maintenance-icon"><i class="bi bi-calendar-plus" aria-hidden="true"></i></span>
                        <div>
                            <strong>Add maintenance period</strong>
                            <small>Existing approved or ongoing bookings cannot be overlapped.</small>
                        </div>
                    </div>
                    <div class="admin-fleet-maintenance-fields">
                        <label>
                            <span>Vehicle</span>
                            <select name="vehicle_id" required>
                                <option value="">Select a vehicle</option>
                                @foreach ($maintenanceVehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}" @selected(old('vehicle_id') === $vehicle->id)>{{ $vehicle->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Start</span>
                            <input type="datetime-local" name="start_datetime" value="{{ old('start_datetime') }}" required>
                        </label>
                        <label>
                            <span>End</span>
                            <input type="datetime-local" name="end_datetime" value="{{ old('end_datetime') }}" required>
                        </label>
                        <label>
                            <span>Reason</span>
                            <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="e.g. Scheduled service" required>
                        </label>
                        <button class="admin-fleet-filter-button" type="submit">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            <span>Add maintenance</span>
                        </button>
                    </div>
                </form>
            @endcan
        </section>
    </main>
@endsection
