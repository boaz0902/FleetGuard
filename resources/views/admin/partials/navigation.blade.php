<aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
    <a class="admin-brand" href="{{ route('admin.dashboard') }}" aria-label="FleetGuard dashboard">
        <span class="admin-brand-mark" aria-hidden="true">
            <svg viewBox="0 0 40 40" fill="none">
                <path d="M7 25.5h26l-2.7-8.1a3 3 0 0 0-2.8-2H12.5a3 3 0 0 0-2.8 2L7 25.5Z" stroke="currentColor" stroke-width="2"/>
                <path d="M9 25.5v4m22-4v4M12 30h16M12 20h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="25.5" r="1.5" fill="currentColor"/>
                <circle cx="28" cy="25.5" r="1.5" fill="currentColor"/>
            </svg>
        </span>
        <span class="admin-brand-name">FLEET<span>GUARD</span></span>
    </a>

    <nav class="admin-navigation" aria-label="Admin sections">
        <div class="admin-navigation-group">
            <p class="admin-sidebar-caption">Overview</p>
            <a class="admin-nav-item {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}"
               href="{{ route('admin.dashboard') }}"
               @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif
               title="Dashboard">
                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                <span class="admin-nav-label">Dashboard</span>
            </a>
        </div>
        <div class="admin-navigation-group">
            <p class="admin-sidebar-caption">Operations</p>
            <a class="admin-nav-item {{ request()->routeIs('admin.bookings.*') ? 'is-active' : '' }}"
               href="{{ route('admin.bookings.index') }}"
               @if (request()->routeIs('admin.bookings.*')) aria-current="page" @endif
               title="Reservations">
                <i class="bi bi-calendar-check" aria-hidden="true"></i>
                <span class="admin-nav-label">Reservations</span>
            </a>
            <a class="admin-nav-item {{ request()->routeIs('admin.rentals.*') ? 'is-active' : '' }}"
               href="{{ route('admin.rentals.index') }}"
               @if (request()->routeIs('admin.rentals.*')) aria-current="page" @endif
               title="Rentals">
                <i class="bi bi-key" aria-hidden="true"></i>
                <span class="admin-nav-label">Rentals</span>
            </a>
            <a class="admin-nav-item {{ request()->routeIs('admin.calendar.*') ? 'is-active' : '' }}"
               href="{{ route('admin.calendar.index') }}"
               @if (request()->routeIs('admin.calendar.*')) aria-current="page" @endif
               title="Availability calendar">
                <i class="bi bi-calendar-week" aria-hidden="true"></i>
                <span class="admin-nav-label">Availability Calendar</span>
            </a>
        </div>
        <div class="admin-navigation-group">
            <p class="admin-sidebar-caption">Management</p>
            <a class="admin-nav-item {{ request()->routeIs('admin.fleet.*') ? 'is-active' : '' }}"
               href="{{ route('admin.fleet.index') }}"
               @if (request()->routeIs('admin.fleet.*')) aria-current="page" @endif
               title="Fleet / Vehicles">
                <i class="bi bi-car-front" aria-hidden="true"></i>
                <span class="admin-nav-label">Fleet / Vehicles</span>
            </a>
            <a class="admin-nav-item {{ request()->routeIs('admin.customers.*') ? 'is-active' : '' }}"
               href="{{ route('admin.customers.index') }}"
               @if (request()->routeIs('admin.customers.*')) aria-current="page" @endif
               title="Customers">
                <i class="bi bi-people" aria-hidden="true"></i>
                <span class="admin-nav-label">Customers</span>
            </a>
            @can('viewAny', \App\Models\AuditLog::class)
                <a class="admin-nav-item {{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}"
                   href="{{ route('admin.reports.index') }}"
                   @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif
                   title="Reports">
                    <i class="bi bi-bar-chart" aria-hidden="true"></i>
                    <span class="admin-nav-label">Reports</span>
                </a>
            @endcan
            <div class="admin-nav-item is-disabled" aria-disabled="true" title="Audit logs — upcoming">
                <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                <span class="admin-nav-label">Audit Logs <small>Upcoming</small></span>
            </div>
        </div>
    </nav>

    <div class="admin-sidebar-footer">
        <div class="admin-sidebar-divider"></div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="admin-nav-item admin-logout" type="submit" title="Sign out">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                <span class="admin-nav-label">Sign out</span>
            </button>
        </form>
    </div>
</aside>
