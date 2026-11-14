<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RentalActionRequest;
use App\Models\Booking;
use App\Services\BookingStatusTransitionService;
use App\Services\RentalManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RentalController extends Controller
{
    public function index(Request $request, RentalManagementService $rentals)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['ready', 'scheduled', 'ongoing', 'due_today', 'overdue', 'completed'])],
            'pickup_from' => ['nullable', 'date_format:Y-m-d'],
            'pickup_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:pickup_from'],
            'sort' => ['nullable', Rule::in(['booking_reference', 'customer', 'vehicle', 'pickup_datetime', 'return_datetime', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $now = Carbon::now(config('app.timezone'));

        return view('admin.rentals.index', [
            'rentals' => $rentals->directory($filters, $now),
            'metrics' => $rentals->metrics($now),
            'filters' => $filters,
            'now' => $now,
        ]);
    }

    public function show(Request $request, Booking $booking, RentalManagementService $rentals)
    {
        $details = $rentals->details(
            $booking,
            Carbon::now(config('app.timezone')),
            $request->user()->isAdmin(),
        );

        return view('admin.rentals.show', [
            ...$details,
            'canManageRental' => $request->user()->can('manageRental', $booking),
        ]);
    }

    public function checkOut(
        RentalActionRequest $request,
        Booking $booking,
        BookingStatusTransitionService $transitions,
    ) {
        $transitions->transition($booking, Booking::STATUS_ONGOING, $request->user());

        return redirect()
            ->route('admin.rentals.show', $booking)
            ->with('status', 'Vehicle checked out and rental started.');
    }

    public function checkIn(
        RentalActionRequest $request,
        Booking $booking,
        BookingStatusTransitionService $transitions,
    ) {
        $transitions->transition($booking, Booking::STATUS_COMPLETED, $request->user());

        return redirect()
            ->route('admin.rentals.show', $booking)
            ->with('status', 'Vehicle checked in and rental completed.');
    }
}
