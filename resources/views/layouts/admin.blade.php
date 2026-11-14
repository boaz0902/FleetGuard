@extends('layouts.app')

@section('content')
    <div class="admin-shell" data-admin-shell>
        <button class="admin-sidebar-backdrop" type="button" aria-label="Close navigation menu" data-admin-backdrop></button>

        @include('admin.partials.navigation')

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-heading">
                    <button class="admin-menu-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="true" aria-label="Collapse sidebar" data-admin-menu-toggle>
                        <i class="bi bi-list" aria-hidden="true"></i>
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                    <span class="admin-topbar-page-icon" aria-hidden="true">
                        <i class="bi bi-@yield('admin-page-icon', 'speedometer2')"></i>
                    </span>
                    <div class="admin-topbar-page-copy">
                        <p class="admin-breadcrumb">
                            <span>FleetGuard</span>
                            <span aria-hidden="true">/</span>
                            @yield('admin-breadcrumb', 'Overview')
                        </p>
                        <h1>@yield('admin-page-title', 'Dashboard')</h1>
                        @hasSection('admin-page-description')
                            <p class="admin-topbar-description">@yield('admin-page-description')</p>
                        @endif
                    </div>
                </div>
                <div class="admin-topbar-meta">
                    <p class="admin-current-date">{{ now('Asia/Manila')->translatedFormat('l, F j, Y') }}</p>
                    <div class="admin-account" role="group" aria-label="Signed in as {{ auth()->user()->name }}, {{ ucfirst(auth()->user()->role) }}">
                        <span class="admin-account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="admin-account-details">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>{{ ucfirst(auth()->user()->role) }}</small>
                        </span>
                    </div>
                </div>
            </header>

            @yield('admin-content')
        </div>
    </div>
@endsection
