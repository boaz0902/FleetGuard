<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleAvailabilityRequest;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;

class VehicleAvailabilityController extends Controller
{
    public function __invoke(VehicleAvailabilityRequest $request, VehicleAvailabilityService $availability)
    {
        $validated = $request->validated();
        $pickup = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['pickup_date']} {$validated['pickup_time']}",
            'Asia/Manila',
        );
        $returned = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['return_date']} {$validated['return_time']}",
            'Asia/Manila',
        );

        return response()->json([
            'data' => $availability->availableVehicles($pickup, $returned)->map(fn ($vehicle) => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'category' => $vehicle->category,
                'category_label' => strtoupper($vehicle->category),
                'passengers' => $vehicle->passenger_capacity.' passengers',
                'transmission' => $vehicle->transmission,
                'fuel' => $vehicle->fuel_type,
                'daily_rate' => $vehicle->daily_rate,
                'image_url' => $vehicle->image_path ? asset($vehicle->image_path) : null,
            ])->values(),
            'meta' => [
                'pickup_datetime' => $pickup->toIso8601String(),
                'return_datetime' => $returned->toIso8601String(),
                'timezone' => 'Asia/Manila',
            ],
        ]);
    }

    public function schedule(Vehicle $vehicle, VehicleAvailabilityService $availability)
    {
        $now = Carbon::now('Asia/Manila');
        $windowEnd = $now->copy()->addDays(90);
        $status = $availability->vehicleAvailabilityStatusesAt($now, [$vehicle->id])[$vehicle->id];
        $periods = collect();

        $vehicle->bookings()
            ->whereIn('status', [Booking::STATUS_APPROVED, Booking::STATUS_ONGOING])
            ->where('return_datetime', '>', $now)
            ->where('pickup_datetime', '<', $windowEnd)
            ->orderBy('pickup_datetime')
            ->get(['status', 'pickup_datetime', 'return_datetime'])
            ->each(function (Booking $booking) use ($periods): void {
                $periods->push([
                    'type' => 'booking',
                    'label' => $booking->status === Booking::STATUS_ONGOING ? 'Rental in progress' : 'Booked',
                    'start' => $booking->pickup_datetime,
                    'end' => $booking->return_datetime,
                ]);
            });

        $vehicle->unavailabilities()
            ->where('end_datetime', '>', $now)
            ->where('start_datetime', '<', $windowEnd)
            ->orderBy('start_datetime')
            ->get(['start_datetime', 'end_datetime'])
            ->each(function ($period) use ($periods): void {
                $periods->push([
                    'type' => 'maintenance',
                    'label' => 'Maintenance',
                    'start' => $period->start_datetime,
                    'end' => $period->end_datetime,
                ]);
            });

        $schedule = $periods
            ->sortBy(fn (array $period): int => $period['start']->getTimestamp())
            ->take(8)
            ->map(fn (array $period): array => [
                'type' => $period['type'],
                'label' => $period['label'],
                'start' => $period['start']->timezone('Asia/Manila')->format('M j, Y · g:i A'),
                'end' => $period['end']->timezone('Asia/Manila')->format('M j, Y · g:i A'),
            ])
            ->values();

        return response()->json([
            'data' => [
                'status' => $status,
                'periods' => $schedule,
                'timezone' => 'Asia/Manila',
                'days_ahead' => 90,
            ],
        ]);
    }
}
