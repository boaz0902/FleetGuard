@extends('layouts.admin')

@section('title', 'Edit Testimonial | FleetGuard')
@section('admin-page-title', 'Edit Testimonial')
@section('admin-page-icon', 'chat-quote')
@section('admin-breadcrumb', 'Customer Reviews / Edit')
@section('admin-page-description', 'Review the customer content and preview how it appears on the homepage.')

@section('admin-content')
    <main class="admin-testimonial-editor" id="main-content">
        <a class="admin-testimonials-back" href="{{ route('admin.testimonials.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> All testimonials</a>
        @if (session('status'))
            <div class="admin-bookings-flash" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="admin-bookings-validation" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ $errors->first() }}</span></div>
        @endif
        @include('admin.testimonials._form', ['testimonial' => $testimonial, 'isNew' => false])
    </main>
@endsection
