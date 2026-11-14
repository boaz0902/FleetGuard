<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleUnavailabilityRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class BookingCalendarController extends Controller
{
    public function index(Request $request, VehicleAvailabilityService $availability)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $start = Carbon::parse($validated['start_date'] ?? today()->toDateString(), 'Asia/Manila')->startOfDay();
        $end = $start->copy()->addDays(14);

        $vehicles = Vehicle::query()
            ->with([
                'bookings' => fn ($query) => $query
                    ->select(['id', 'vehicle_id', 'booking_reference', 'pickup_datetime', 'return_datetime', 'status'])
                    ->where('pickup_datetime', '<', $end)
                    ->where('return_datetime', '>', $start)
                    ->orderBy('pickup_datetime'),
                'unavailabilities' => fn ($query) => $query
                    ->where('start_datetime', '<', $end)
                    ->where('end_datetime', '>', $start)
                    ->orderBy('start_datetime'),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.calendar.index', [
            'vehicles' => $vehicles,
            'days' => collect(range(0, 13))->map(fn (int $offset) => $start->copy()->addDays($offset)),
            'fleetStatus' => $availability->fleetStatusAt(Carbon::now('Asia/Manila')),
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function storeUnavailability(
        StoreVehicleUnavailabilityRequest $request,
        VehicleAvailabilityService $availability,
    )
    {
        $this->authorize('manage', Vehicle::class);
        $validated = $request->validated();
        $start = Carbon::createFromFormat(
            'Y-m-d\TH:i',
            $validated['start_datetime'],
            'Asia/Manila',
        );
        $end = Carbon::createFromFormat(
            'Y-m-d\TH:i',
            $validated['end_datetime'],
            'Asia/Manila',
        );

        DB::transaction(function () use ($validated, $start, $end, $availability): void {
            $vehicle = Vehicle::query()->whereKey($validated['vehicle_id'])->lockForUpdate()->firstOrFail();

            if ($availability->hasBookingConflict($vehicle, $start, $end)) {
                throw ValidationException::withMessages([
                    'start_datetime' => ['This maintenance period overlaps an approved or ongoing reservation.'],
                ]);
            }

            $unavailability = VehicleUnavailability::query()->create([
                'vehicle_id' => $vehicle->id,
                'start_datetime' => $start,
                'end_datetime' => $end,
                'reason' => trim($validated['reason']),
            ]);

            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => 'vehicle_unavailability_created',
                'subject_type' => VehicleUnavailability::class,
                'subject_id' => $unavailability->id,
                'changes' => [
                    'vehicle_id' => $unavailability->vehicle_id,
                    'start_datetime' => $unavailability->start_datetime->toDateTimeString(),
                    'end_datetime' => $unavailability->end_datetime->toDateTimeString(),
                    'reason' => $unavailability->reason,
                ],
                'created_at' => now(),
            ]);
        });

        $destination = $request->routeIs('admin.fleet.maintenance.store')
            ? 'admin.fleet.index'
            : 'admin.calendar.index';

        return redirect()->route($destination)->with('status', 'Maintenance period added.');
    }

    public function destroyUnavailability(Request $request, VehicleUnavailability $unavailability)
    {
        $this->authorize('manage', Vehicle::class);

        DB::transaction(function () use ($unavailability): void {
            Vehicle::query()->whereKey($unavailability->vehicle_id)->lockForUpdate()->firstOrFail();
            $lockedUnavailability = VehicleUnavailability::query()
                ->whereKey($unavailability->id)
                ->lockForUpdate()
                ->firstOrFail();
            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => 'vehicle_unavailability_removed',
                'subject_type' => VehicleUnavailability::class,
                'subject_id' => $lockedUnavailability->id,
                'changes' => [
                    'vehicle_id' => $lockedUnavailability->vehicle_id,
                    'start_datetime' => $lockedUnavailability->start_datetime->toDateTimeString(),
                    'end_datetime' => $lockedUnavailability->end_datetime->toDateTimeString(),
                    'reason' => $lockedUnavailability->reason,
                ],
                'created_at' => now(),
            ]);
            $lockedUnavailability->delete();
        });

        $destination = $request->routeIs('admin.fleet.maintenance.destroy')
            ? 'admin.fleet.index'
            : 'admin.calendar.index';

        return redirect()->route($destination)->with('status', 'Maintenance period removed.');
    }
}
