@extends('layouts.admin')

@section('title', 'Edit ' . $vehicle->name . ' | FleetGuard')
@section('admin-page-title', 'Edit vehicle')
@section('admin-page-icon', 'pencil-square')
@section('admin-breadcrumb', 'Fleet')
@section('admin-page-description', 'Update vehicle specifications and rental rate without changing existing bookings.')

@section('admin-content')
    <main class="admin-fleet-form-page" id="main-content">
        @if ($errors->any())
            <div class="admin-fleet-alert" role="alert">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif
        <div class="admin-fleet-form-page-heading">
            <a class="admin-fleet-secondary-link" href="{{ route('admin.fleet.show', $vehicle) }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <span>Back to vehicle details</span>
            </a>
        </div>
        <section class="admin-fleet-form-panel" aria-labelledby="edit-vehicle-heading">
            <div class="admin-fleet-form-panel-heading">
                <span class="admin-fleet-detail-icon"><i class="bi bi-car-front" aria-hidden="true"></i></span>
                <div>
                    <p class="admin-fleet-eyebrow">{{ $vehicle->name }}</p>
                    <h2 id="edit-vehicle-heading">Vehicle information</h2>
                </div>
            </div>
            @include('admin.fleet._form')
        </section>
    </main>
@endsection
