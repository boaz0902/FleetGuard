<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVehicleRequest;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FleetController extends Controller
{
    public function index(Request $request, VehicleAvailabilityService $availability): View
    {
        $this->authorize('viewAny', Vehicle::class);

        $categories = $this->categories();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::in($categories->all())],
            'status' => ['nullable', Rule::in([
                Vehicle::STATUS_ACTIVE,
                Vehicle::STATUS_INACTIVE,
                Vehicle::STATUS_MAINTENANCE,
            ])],
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'category', 'daily_rate', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $now = Carbon::now('Asia/Manila');
        $availabilityStatuses = $availability->vehicleAvailabilityStatusesAt($now);
        $availabilityCounts = array_count_values($availabilityStatuses);
        $fleetStatus = [
            'total' => count($availabilityStatuses),
            'available' => $availabilityCounts['available'] ?? 0,
            'reserved' => $availabilityCounts['reserved'] ?? 0,
            'ongoing' => $availabilityCounts['ongoing'] ?? 0,
            'maintenance' => $availabilityCounts['maintenance'] ?? 0,
            'inactive' => $availabilityCounts['inactive'] ?? 0,
        ];
        $fleetStatus['active'] = Vehicle::query()->where('status', Vehicle::STATUS_ACTIVE)->count();

        $vehiclesQuery = Vehicle::query();
        $search = trim($validated['search'] ?? '');

        if ($search !== '') {
            $searchPattern = "%{$search}%";
            $rateSearch = str_replace(['₱', ',', ' '], '', $search);

            if (str_contains($rateSearch, '.')) {
                $rateSearch = rtrim(rtrim($rateSearch, '0'), '.');
            }

            $dateSearch = $this->parseSearchDate($search);
            $monthDaySearch = $this->parseSearchMonthDay($search);
            $monthSearch = $this->parseSearchMonth($search);
            $matchingAvailabilityIds = [];

            foreach ($availabilityStatuses as $vehicleId => $availabilityStatus) {
                if (stripos($availabilityStatus, $search) !== false) {
                    $matchingAvailabilityIds[] = $vehicleId;
                }
            }

            $vehiclesQuery->where(function (Builder $query) use (
                $searchPattern,
                $rateSearch,
                $dateSearch,
                $monthDaySearch,
                $monthSearch,
                $matchingAvailabilityIds,
            ): void {
                $query
                    ->where('name', 'like', $searchPattern)
                    ->orWhere('brand', 'like', $searchPattern)
                    ->orWhere('model', 'like', $searchPattern)
                    ->orWhere('category', 'like', $searchPattern)
                    ->orWhere('status', 'like', $searchPattern)
                    ->orWhere('daily_rate', 'like', "%{$rateSearch}%")
                    ->orWhere('created_at', 'like', $searchPattern);

                if ($dateSearch !== null) {
                    $query->orWhereDate('created_at', $dateSearch);
                }

                if ($monthDaySearch !== null) {
                    $query
                        ->orWhere(function (Builder $dateQuery) use ($monthDaySearch): void {
                            $dateQuery
                                ->whereMonth('created_at', $monthDaySearch['month'])
                                ->whereDay('created_at', $monthDaySearch['day']);
                        });
                }

                if ($monthSearch !== null) {
                    $query->orWhereMonth('created_at', $monthSearch);
                }

                if ($matchingAvailabilityIds !== []) {
                    $query->orWhereIn('id', $matchingAvailabilityIds);
                }
            });
        }

        $vehiclesQuery
            ->when($validated['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $vehicles = $vehiclesQuery
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.fleet.index', [
            'vehicles' => $vehicles,
            'categories' => $categories,
            'filters' => $validated,
            'availabilityStatuses' => array_intersect_key(
                $availabilityStatuses,
                array_flip($vehicles->getCollection()->pluck('id')->all()),
            ),
            'fleetStatus' => $fleetStatus,
            'maintenanceVehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'maintenanceRecords' => VehicleUnavailability::query()
                ->with('vehicle:id,name')
                ->where('end_datetime', '>', $now)
                ->orderBy('start_datetime')
                ->paginate(8, ['*'], 'maintenance_page')
                ->withQueryString(),
            'images' => $this->availableImages(),
            'now' => $now,
        ]);
    }

    private function parseSearchDate(string $search): ?string
    {
        foreach (['Y-m-d', 'M d, Y'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                $date = Carbon::createFromFormat($format, $search);

                if ($date !== false) {
                    return $date->toDateString();
                }
            }
        }

        return null;
    }

    private function parseSearchMonthDay(string $search): ?array
    {
        foreach (['M d', 'M j'] as $format) {
            if (Carbon::hasFormat($search, $format)) {
                $date = Carbon::createFromFormat($format, $search);

                if ($date !== false) {
                    return ['month' => $date->month, 'day' => $date->day];
                }
            }
        }

        return null;
    }

    private function parseSearchMonth(string $search): ?int
    {
        return [
            'jan' => 1,
            'january' => 1,
            'feb' => 2,
            'february' => 2,
            'mar' => 3,
            'march' => 3,
            'apr' => 4,
            'april' => 4,
            'may' => 5,
            'jun' => 6,
            'june' => 6,
            'jul' => 7,
            'july' => 7,
            'aug' => 8,
            'august' => 8,
            'sep' => 9,
            'september' => 9,
            'oct' => 10,
            'october' => 10,
            'nov' => 11,
            'november' => 11,
            'dec' => 12,
            'december' => 12,
        ][strtolower($search)] ?? null;
    }

    public function create(): View
    {
        $this->authorize('create', Vehicle::class);

        return view('admin.fleet.create', [
            'vehicle' => new Vehicle,
            'categories' => $this->categories(),
            'images' => $this->availableImages(),
        ]);
    }

    public function store(SaveVehicleRequest $request): RedirectResponse
    {
        $this->authorize('create', Vehicle::class);
        $validated = $request->validated();
        $baseId = Str::limit(Str::slug($validated['brand'].'-'.$validated['model']), 180, '');
        $vehicleId = ($baseId !== '' ? $baseId : 'vehicle').'-'.Str::lower(Str::random(8));

        $vehicle = DB::transaction(function () use ($validated, $vehicleId): Vehicle {
            $vehicle = Vehicle::query()->create([
                'id' => $vehicleId,
                ...$validated,
            ]);
            $changes = collect($validated)
                ->map(fn (mixed $value): array => ['from' => null, 'to' => $value])
                ->all();

            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => 'vehicle_created',
                'subject_type' => Vehicle::class,
                'subject_id' => $vehicle->id,
                'changes' => $changes,
                'created_at' => now(),
            ]);

            return $vehicle;
        });

        return redirect()->route('admin.fleet.show', $vehicle)->with('status', 'Vehicle added to the fleet.');
    }

    public function show(Request $request, Vehicle $vehicle, VehicleAvailabilityService $availability): View
    {
        $this->authorize('view', $vehicle);

        $now = Carbon::now('Asia/Manila');
        $canReviewBookings = $request->user()->can('review', Booking::class);
        $bookingQuery = $vehicle->bookings();
        $upcomingBookings = collect();
        $bookingHistory = collect();
        $totalBookingCount = null;
        $upcomingBookingCount = null;

        if ($canReviewBookings) {
            $totalBookingCount = (clone $bookingQuery)->count();
            $upcomingBookingCount = (clone $bookingQuery)
                ->where('status', Booking::STATUS_APPROVED)
                ->where('pickup_datetime', '>', $now)
                ->count();
            $upcomingBookings = (clone $bookingQuery)
                ->whereIn('status', [Booking::STATUS_APPROVED, Booking::STATUS_ONGOING])
                ->where('return_datetime', '>', $now)
                ->orderBy('pickup_datetime')
                ->limit(10)
                ->get(['id', 'booking_reference', 'status', 'pickup_datetime', 'return_datetime']);
            $bookingHistory = $bookingQuery
                ->latest('created_at')
                ->limit(15)
                ->get(['id', 'booking_reference', 'status', 'pickup_datetime', 'return_datetime', 'created_at']);
        }

        $maintenanceRecords = $vehicle->unavailabilities()->orderByDesc('start_datetime')->get();

        return view('admin.fleet.show', [
            'vehicle' => $vehicle,
            'availabilityStatus' => $availability->vehicleAvailabilityStatusesAt($now, [$vehicle->id])[$vehicle->id] ?? $vehicle->status,
            'upcomingBookings' => $upcomingBookings,
            'bookingHistory' => $bookingHistory,
            'canReviewBookings' => $canReviewBookings,
            'totalBookingCount' => $totalBookingCount,
            'upcomingBookingCount' => $upcomingBookingCount,
            'maintenanceRecords' => $maintenanceRecords,
            'maintenanceCreators' => AuditLog::query()
                ->with('user:id,name')
                ->where('action', 'vehicle_unavailability_created')
                ->where('subject_type', VehicleUnavailability::class)
                ->whereIn('subject_id', $maintenanceRecords->modelKeys())
                ->get()
                ->keyBy('subject_id'),
            'maintenanceHistory' => AuditLog::query()
                ->with('user')
                ->whereIn('action', ['vehicle_unavailability_created', 'vehicle_unavailability_removed'])
                ->where('changes->vehicle_id', $vehicle->id)
                ->latest('created_at')
                ->limit(20)
                ->get(),
            'vehicleHistory' => $vehicle->auditLogs()
                ->with('user')
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function edit(Vehicle $vehicle): View
    {
        $this->authorize('update', $vehicle);

        return view('admin.fleet.edit', [
            'vehicle' => $vehicle,
            'categories' => $this->categories(),
            'images' => $this->availableImages(),
        ]);
    }

    public function update(
        SaveVehicleRequest $request,
        Vehicle $vehicle,
        VehicleAvailabilityService $availability,
    ): RedirectResponse {
        $this->authorize('update', $vehicle);
        $validated = $request->validated();

        $hasChanges = DB::transaction(function () use ($vehicle, $validated, $availability): bool {
            $lockedVehicle = Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $lockedVehicle->status;

            if (
                $validated['status'] !== $previousStatus
                && in_array($validated['status'], [Vehicle::STATUS_INACTIVE, Vehicle::STATUS_MAINTENANCE], true)
                && $availability->hasUpcomingConfirmedBooking($lockedVehicle, Carbon::now('Asia/Manila'))
            ) {
                throw ValidationException::withMessages([
                    'status' => ['Resolve approved or ongoing reservations before deactivating or placing this vehicle under maintenance.'],
                ]);
            }

            $changes = [];

            foreach ($validated as $attribute => $newValue) {
                $oldValue = $lockedVehicle->getAttribute($attribute);

                if ($attribute === 'daily_rate') {
                    $oldValue = number_format((float) $oldValue, 2, '.', '');
                    $newValue = number_format((float) $newValue, 2, '.', '');
                } elseif ($attribute === 'passenger_capacity') {
                    $oldValue = (int) $oldValue;
                    $newValue = (int) $newValue;
                }

                if ($oldValue !== $newValue) {
                    $changes[$attribute] = ['from' => $oldValue, 'to' => $newValue];
                }
            }

            if ($changes === []) {
                return false;
            }

            $lockedVehicle->fill($validated);
            $lockedVehicle->save();
            $action = match ($lockedVehicle->status) {
                Vehicle::STATUS_ACTIVE => isset($changes['status']) ? 'vehicle_activated' : 'vehicle_updated',
                Vehicle::STATUS_INACTIVE => isset($changes['status']) ? 'vehicle_deactivated' : 'vehicle_updated',
                Vehicle::STATUS_MAINTENANCE => isset($changes['status']) ? 'vehicle_status_changed' : 'vehicle_updated',
            };

            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => Vehicle::class,
                'subject_id' => $lockedVehicle->id,
                'changes' => $changes,
                'created_at' => now(),
            ]);

            return true;
        });

        $message = $hasChanges ? 'Vehicle information updated.' : 'No vehicle information changed.';

        return redirect()->route('admin.fleet.show', $vehicle)->with('status', $message);
    }

    private function categories(): Collection
    {
        return Vehicle::query()
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();
    }

    private function availableImages(): Collection
    {
        return collect(File::files(public_path('images/fleet')))
            ->map(fn (\SplFileInfo $file): string => 'images/fleet/'.$file->getFilename())
            ->values();
    }
}
