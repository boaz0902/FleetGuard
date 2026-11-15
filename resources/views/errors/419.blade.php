@extends('layouts.app')

@section('title', 'Page Expired | FleetGuard')
@section('meta-description', 'Your FleetGuard page session expired. Return to the site and try again.')

@section('content')
    @include('partials.public-error-page', [
        'code' => '419',
        'title' => 'This page has expired.',
        'message' => 'For your security, this page session has ended. Return to FleetGuard and try again.',
    ])
@endsection
