<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleAvailabilityRequest;
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
}
