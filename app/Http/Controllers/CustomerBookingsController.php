<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelCustomerBookingRequest;
use App\Models\Booking;
use App\Services\BookingStatusTransitionService;
use App\Services\CustomerBookingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerBookingsController extends Controller
{
    public function index(Request $request, CustomerBookingService $bookings): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            if (! $request->session()->has('url.intended')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()
                ->route('verification.notice')
                ->with('status', 'Please verify your email to continue');
        }

        $now = Carbon::now(config('app.timezone'));
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(CustomerBookingService::FILTERS)],
        ]);
        $activeFilter = $validated['status'] ?? 'all';
        $query = $bookings->forUser($user)->with('vehicle');

        if ($activeFilter === 'closed') {
            $query->whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED]);
        } elseif ($activeFilter !== 'all') {
            $query->where('status', $activeFilter);
        }

        return view('customer.bookings.index', [
            'verified' => true,
            'bookings' => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(8)
                ->withQueryString(),
            'counts' => $bookings->statusCounts($user),
            'summary' => $bookings->summary($user, $now),
            'activeFilter' => $activeFilter,
        ]);
    }

    public function cancel(
        CancelCustomerBookingRequest $request,
        Booking $booking,
        BookingStatusTransitionService $transitions,
    ): RedirectResponse {
        $this->authorize('cancelByCustomer', $booking);

        $reason = $request->validated('reason') ?? '';

        try {
            $transitions->transition(
                $booking,
                Booking::STATUS_CANCELLED,
                $request->user(),
                '',
                'booking_cancelled_by_customer',
                [
                    'user_id' => $request->user()->id,
                    'customer_id' => $booking->customer_id,
                    'booking_id' => $booking->id,
                    'reason' => $reason,
                ],
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('bookings.show', $booking)
                ->withErrors([
                    'status' => 'Only pending requests can be cancelled. Approved bookings cannot be cancelled online; contact FleetGuard for assistance.',
                ]);
        }

        return redirect()
            ->route('bookings.show', $booking)
            ->with('status', 'Your pending booking request has been cancelled.');
    }
}
