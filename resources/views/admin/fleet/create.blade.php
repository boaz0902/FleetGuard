@extends('layouts.admin')

@section('title', 'Add Vehicle | FleetGuard')
@section('admin-page-title', 'Add vehicle')
@section('admin-page-icon', 'plus-circle')
@section('admin-breadcrumb', 'Fleet')
@section('admin-page-description', 'Add a vehicle using the fields supported by the current inventory database.')

@section('admin-content')
    <main class="admin-fleet-form-page" id="main-content">
        @if ($errors->any())
            <div class="admin-fleet-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif
        <div class="admin-fleet-form-page-heading">
            <a class="admin-fleet-secondary-link" href="{{ route('admin.fleet.index') }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <span>Back to fleet</span>
            </a>
        </div>
        <section class="admin-fleet-form-panel" aria-labelledby="create-vehicle-heading">
            <div class="admin-fleet-form-panel-heading">
                <span class="admin-fleet-detail-icon"><i class="bi bi-car-front" aria-hidden="true"></i></span>
                <div>
                    <p class="admin-fleet-eyebrow">New inventory</p>
                    <h2 id="create-vehicle-heading">Vehicle information</h2>
                </div>
            </div>
            @include('admin.fleet._form')
        </section>
    </main>
@endsection
