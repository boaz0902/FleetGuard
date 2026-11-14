<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBookingStatusRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Services\VehicleAvailabilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingManagementController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in([
                    Booking::STATUS_PENDING,
                    Booking::STATUS_APPROVED,
                    Booking::STATUS_REJECTED,
                    Booking::STATUS_ONGOING,
                    Booking::STATUS_COMPLETED,
                    Booking::STATUS_CANCELLED,
                ]),
            ],
            'vehicle_id' => ['nullable', 'string', 'exists:vehicles,id'],
            'pickup_from' => ['nullable', 'date_format:Y-m-d'],
            'pickup_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:pickup_from'],
        ]);

        $statusCounts = Booking::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $bookingsQuery = Booking::query()->with(['customer', 'vehicle']);
        $search = trim($validated['search'] ?? '');
        $searchCustomerPhone = $request->user()->can('review', Booking::class);

        if ($search !== '') {
            $bookingsQuery->where(function (Builder $query) use ($search, $searchCustomerPhone): void {
                $query
                    ->where('booking_reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search, $searchCustomerPhone): void {
                        $customerQuery->where('full_name', 'like', "%{$search}%");

                        if ($searchCustomerPhone) {
                            $customerQuery->orWhere('mobile_number', 'like', "%{$search}%");
                        }
                    });
            });
        }

        $bookingsQuery
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['vehicle_id'] ?? null, fn (Builder $query, string $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($validated['pickup_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('pickup_datetime', '>=', $date))
            ->when($validated['pickup_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('pickup_datetime', '<=', $date));

        return view('admin.bookings.index', [
            'bookings' => $bookingsQuery
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name', 'category']),
            'metrics' => [
                'total' => $statusCounts->sum(),
                'pending' => $statusCounts->get(Booking::STATUS_PENDING, 0),
                'approved' => $statusCounts->get(Booking::STATUS_APPROVED, 0),
                'ongoing' => $statusCounts->get(Booking::STATUS_ONGOING, 0),
                'completed' => $statusCounts->get(Booking::STATUS_COMPLETED, 0),
            ],
            'filters' => $validated,
        ]);
    }

    public function show(Booking $booking)
    {
        $this->authorize('review', Booking::class);

        $booking->load([
            'customer',
            'vehicle',
            'auditLogs' => fn ($query) => $query
                ->with('user')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'allowedTransitions' => $booking->getStatusTransitions()[$booking->status] ?? [],
        ]);
    }

    public function updateStatus(
        UpdateBookingStatusRequest $request,
        Booking $booking,
        VehicleAvailabilityService $availability,
    )
    {
        $this->authorize('review', Booking::class);

        $validated = $request->validated();
        $status = $validated['status'];
        $reason = trim((string) ($validated['admin_remarks'] ?? ''));

        $booking = DB::transaction(function () use ($booking, $status, $reason, $availability) {
            $vehicle = Vehicle::query()
                ->whereKey($booking->vehicle_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $lockedBooking->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => ['This status transition is not allowed.'],
                ]);
            }

            if ($status === Booking::STATUS_APPROVED) {
                if (! $availability->isAvailable(
                    $vehicle,
                    $lockedBooking->pickup_datetime,
                    $lockedBooking->return_datetime,
                    $lockedBooking->id,
                )) {
                    throw ValidationException::withMessages([
                        'status' => ['This booking conflicts with an approved or ongoing booking or a maintenance period.'],
                    ]);
                }
            }

            $previousStatus = $lockedBooking->status;
            $updates = ['status' => $status];

            if ($reason !== '') {
                $updates['admin_remarks'] = $reason;
            }

            $lockedBooking->update($updates);
            $action = match ($status) {
                Booking::STATUS_APPROVED => 'booking_approved',
                Booking::STATUS_REJECTED => 'booking_rejected',
                Booking::STATUS_CANCELLED => 'booking_cancelled',
                Booking::STATUS_ONGOING => 'booking_marked_ongoing',
                Booking::STATUS_COMPLETED => 'booking_marked_completed',
            };
            $changes = [
                'from' => $previousStatus,
                'to' => $status,
            ];

            if ($reason !== '') {
                $changes['reason'] = $reason;
            }

            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => Booking::class,
                'subject_id' => $lockedBooking->id,
                'changes' => $changes,
                'created_at' => now(),
            ]);

            return $lockedBooking;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Booking status updated successfully.',
                'booking' => $booking->fresh()->load(['customer', 'vehicle']),
            ]);
        }

        return redirect()->back()->with('status', 'Reservation status updated successfully.');
    }
}
