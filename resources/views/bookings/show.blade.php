@extends('layouts.app')

@section('title', 'Booking Details')

@section('content')
    <div class="mx-auto max-w-4xl px-6 py-10">
        <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <p class="text-sm uppercase tracking-[0.25em] text-cyan-500">Booking request</p>
            <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ $booking->booking_reference }}</h1>

            <dl class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <dt class="text-sm text-slate-500">Customer</dt>
                    <dd class="mt-1 text-lg font-medium text-slate-900">{{ $booking->customer->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Vehicle</dt>
                    <dd class="mt-1 text-lg font-medium text-slate-900">{{ $booking->vehicle->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Pickup</dt>
                    <dd class="mt-1 text-slate-700">{{ $booking->pickup_datetime->format('M d, Y H:i') }} · {{ $booking->pickup_location }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Return</dt>
                    <dd class="mt-1 text-slate-700">{{ $booking->return_datetime->format('M d, Y H:i') }} · {{ $booking->return_location }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Status</dt>
                    <dd class="mt-1 text-slate-700">{{ $booking->status }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Estimated total</dt>
                    <dd class="mt-1 text-slate-700">₱{{ number_format((float) $booking->estimated_total, 2) }}</dd>
                </div>
            </dl>

            @if ($booking->customer_notes)
                <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-medium text-slate-900">Customer notes</p>
                    <p class="mt-2">{{ $booking->customer_notes }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
