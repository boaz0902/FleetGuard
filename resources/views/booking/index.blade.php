@extends('layouts.app')

@section('title', 'Book a Vehicle | FleetGuard')
@section('meta-description', 'Complete a secure FleetGuard booking request for vehicle and trip review.')

@section('content')
    @include('partials.public-navigation')
    <main id="main-content" class="standalone-booking">
        @include('sections.booking-experience', ['vehicles' => []])
    </main>
@endsection
