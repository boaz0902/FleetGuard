<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VehicleAvailabilityService
{
    public function availableVehicles(CarbonInterface $pickup, CarbonInterface $returned): Collection
    {
        return Vehicle::query()
            ->where('status', Vehicle::STATUS_ACTIVE)
            ->whereDoesntHave('bookings', function (Builder $query) use ($pickup, $returned) {
                $this->applyBookingConflicts($query, $pickup, $returned);
            })
            ->whereDoesntHave('unavailabilities', function (Builder $query) use ($pickup, $returned) {
                $this->applyOverlap($query, 'start_datetime', 'end_datetime', $pickup, $returned);
            })
            ->orderBy('name')
            ->get();
    }

    public function isAvailable(
        Vehicle $vehicle,
        CarbonInterface $pickup,
        CarbonInterface $returned,
        ?int $exceptBookingId = null,
    ): bool {
        if ($vehicle->status !== Vehicle::STATUS_ACTIVE) {
            return false;
        }

        if ($this->hasBookingConflict($vehicle, $pickup, $returned, $exceptBookingId)) {
            return false;
        }

        return ! $vehicle->unavailabilities()
            ->where('start_datetime', '<', $returned)
            ->where('end_datetime', '>', $pickup)
            ->exists();
    }

    public function hasBookingConflict(
        Vehicle $vehicle,
        CarbonInterface $pickup,
        CarbonInterface $returned,
        ?int $exceptBookingId = null,
    ): bool {
        $bookingConflicts = Booking::query()
            ->where('vehicle_id', $vehicle->id)
            ->when($exceptBookingId !== null, fn (Builder $query) => $query->where('id', '<>', $exceptBookingId));

        $this->applyBookingConflicts($bookingConflicts, $pickup, $returned);

        return $bookingConflicts->exists();
    }

    public function hasUpcomingConfirmedBooking(Vehicle $vehicle, CarbonInterface $instant): bool
    {
        return $vehicle->bookings()
            ->whereIn('status', [Booking::STATUS_APPROVED, Booking::STATUS_ONGOING])
            ->where('return_datetime', '>', $instant)
            ->exists();
    }

    /**
     * @return array{total:int, available:int, reserved:int, ongoing:int, maintenance:int, inactive:int}
     */
    public function fleetStatusAt(CarbonInterface $instant): array
    {
        $vehicleStatuses = $this->vehicleAvailabilityStatusesAt($instant);
        $fleetStatus = [
            'available' => 0,
            'reserved' => 0,
            'ongoing' => 0,
            'maintenance' => 0,
            'inactive' => 0,
        ];

        foreach ($vehicleStatuses as $status) {
            $fleetStatus[$status]++;
        }

        return ['total' => count($vehicleStatuses), ...$fleetStatus];
    }

    /**
     * @param  array<int, string>|null  $vehicleIds
     * @return array<string, string>
     */
    public function vehicleAvailabilityStatusesAt(CarbonInterface $instant, ?array $vehicleIds = null): array
    {
        $instantEnd = Carbon::instance($instant)->addSecond();
        $vehicles = Vehicle::query()
            ->select(['id', 'status'])
            ->when($vehicleIds !== null, fn (Builder $query) => $query->whereKey($vehicleIds))
            ->withCount([
                'bookings as ongoing_now_count' => fn (Builder $query) => $query
                    ->where('status', Booking::STATUS_ONGOING)
                    ->overlappingPeriod($instant, $instantEnd),
                'bookings as approved_now_count' => fn (Builder $query) => $query
                    ->where('status', Booking::STATUS_APPROVED)
                    ->overlappingPeriod($instant, $instantEnd),
                'unavailabilities as maintenance_now_count' => fn (Builder $query) => $query
                    ->where('start_datetime', '<', $instantEnd)
                    ->where('end_datetime', '>', $instant),
            ])
            ->get();

        $vehicleStatuses = [];

        foreach ($vehicles as $vehicle) {
            if ($vehicle->status === Vehicle::STATUS_INACTIVE) {
                $vehicleStatuses[$vehicle->id] = 'inactive';
            } elseif (
                $vehicle->status === Vehicle::STATUS_MAINTENANCE
                || $vehicle->maintenance_now_count > 0
            ) {
                $vehicleStatuses[$vehicle->id] = 'maintenance';
            } elseif ($vehicle->ongoing_now_count > 0) {
                $vehicleStatuses[$vehicle->id] = 'ongoing';
            } elseif ($vehicle->approved_now_count > 0) {
                $vehicleStatuses[$vehicle->id] = 'reserved';
            } else {
                $vehicleStatuses[$vehicle->id] = 'available';
            }
        }

        return $vehicleStatuses;
    }

    private function applyBookingConflicts(
        Builder $query,
        CarbonInterface $pickup,
        CarbonInterface $returned,
    ): void {
        $query
            ->whereIn('status', [Booking::STATUS_APPROVED, Booking::STATUS_ONGOING])
            ->overlappingPeriod($pickup, $returned);
    }

    private function applyOverlap(
        Builder $query,
        string $startColumn,
        string $endColumn,
        CarbonInterface $pickup,
        CarbonInterface $returned,
    ): void {
        $query
            ->where($startColumn, '<', $returned)
            ->where($endColumn, '>', $pickup);
    }
}
