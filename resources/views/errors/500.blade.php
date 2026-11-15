@extends('layouts.app')

@section('title', 'Something Went Wrong | FleetGuard')
@section('meta-description', 'FleetGuard could not complete that request. Please try again later.')

@section('content')
    @include('partials.public-error-page', [
        'code' => '500',
        'title' => 'We hit a temporary issue.',
        'message' => 'FleetGuard could not complete that request. Please try again in a little while.',
    ])
@endsection
