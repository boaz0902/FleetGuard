@extends('layouts.app')

@section('title', 'Page Not Found | FleetGuard')
@section('meta-description', 'The FleetGuard page you requested could not be found.')

@section('content')
    @include('partials.public-error-page', [
        'code' => '404',
        'title' => 'We can\'t find that page.',
        'message' => 'The link may be out of date, or the page may have moved. Head back to FleetGuard to continue.',
    ])
@endsection
