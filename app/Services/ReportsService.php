<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleUnavailability;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    private const STATUSES = [
        Booking::STATUS_PENDING,
        Booking::STATUS_APPROVED,
        Booking::STATUS_ONGOING,
        Booking::STATUS_COMPLETED,
        Booking::STATUS_REJECTED,
        Booking::STATUS_CANCELLED,
    ];

    private const CONFIRMED_STATUSES = [
        Booking::STATUS_APPROVED,
        Booking::STATUS_ONGOING,
        Booking::STATUS_COMPLETED,
    ];

    public function __construct(private readonly VehicleAvailabilityService $availability) {}

    public function report(array $filters, CarbonInterface $now): array
    {
        $start = Carbon::parse($filters['start_date'], config('app.timezone'))->startOfDay();
        $end = Carbon::parse($filters['end_date'], config('app.timezone'))->startOfDay();
        $endExclusive = $end->copy()->addDay();
        $rangeDays = (int) (($endExclusive->getTimestamp() - $start->getTimestamp()) / 86400);

        $overview = $this->overview($filters, $start, $endExclusive);
        $statusBreakdown = $this->statusBreakdown($filters, $start, $endExclusive);
        $dateCounts = $this->reservationDateCounts($filters, $start, $endExclusive);
        $trend = $this->reservationTrend($dateCounts, $start, $end, $rangeDays);
        [$vehicleRows, $fleetTotals, $maintenanceDays] = $this->vehicleUtilization($filters, $start, $endExclusive, $rangeDays, $now);
        $customers = $this->customerInsights($filters, $start, $endExclusive);
        $maintenanceQuery = $this->maintenanceQuery($filters, $start, $endExclusive);
        $maintenanceTotals = [
            'periods' => (int) $maintenanceQuery->count(),
            'days' => round(array_sum($maintenanceDays), 2),
            'vehicles_currently_under_maintenance' => $vehicleRows->where('current_state', 'maintenance')->count(),
        ];
        $maintenancePeriods = $this->maintenanceQuery($filters, $start, $endExclusive)
            ->with('vehicle:id,name,category')
            ->orderBy('start_datetime')
            ->orderBy('id')
            ->paginate(10, ['*'], 'maintenance_page')
            ->withQueryString();

        $overview['fleet_utilization'] = $fleetTotals['utilization'];

        return [
            'overview' => $overview,
            'trend' => $trend,
            'status_breakdown' => $statusBreakdown,
            'fleet' => [
                ...$fleetTotals,
                'vehicles' => $vehicleRows,
                'range_days' => $rangeDays,
            ],
            'popular_vehicles' => $this->popularVehicles($filters, $start, $endExclusive),
            'popular_categories' => $this->popularCategories($filters, $start, $endExclusive),
            'customers' => $customers,
            'maintenance' => [
                'period_count' => $maintenanceTotals['periods'],
                'days' => $maintenanceTotals['days'],
                'vehicles_currently_under_maintenance' => $maintenanceTotals['vehicles_currently_under_maintenance'],
                'days_by_vehicle' => $maintenanceDays,
                'periods' => $maintenancePeriods,
            ],
            'operations' => $this->rentalOperations($filters, $start, $endExclusive, $now),
            'vehicle_options' => $this->vehicleOptions($filters),
            'category_options' => Vehicle::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'date_range' => [
                'start' => $start,
                'end' => $end,
                'days' => $rangeDays,
            ],
        ];
    }

    public function exportRows(array $filters, CarbonInterface $now): Generator
    {
        $report = $this->report($filters, $now);
        $metrics = $report['overview'];

        yield ['Overview', 'Date range', '', '', '', '', $filters['start_date'].' to '.$filters['end_date'], 'Pickup date basis'];
        yield ['Overview', 'Total reservations', '', '', $metrics['total_reservations'], '', '', 'All statuses'];
        yield ['Overview', 'Approved', '', '', $metrics['approved'], '', '', 'Current approved status'];
        yield ['Overview', 'Completed', '', '', $metrics['completed'], '', '', 'Current completed status'];
        yield ['Overview', 'Cancelled / rejected', '', '', $metrics['cancelled_rejected'], '', '', 'Combined current statuses'];
        yield ['Overview', 'Approval rate', '', '', '', '', $metrics['approval_rate'], 'Confirmed outcomes divided by confirmed plus rejected; pending and cancelled excluded'];
        yield ['Overview', 'Estimated Revenue (from booking snapshots)', '', '', '', '', $metrics['estimated_revenue'], 'Not payment data'];
        yield ['Overview', 'Completed Estimated Revenue (from booking snapshots)', '', '', '', '', $metrics['completed_estimated_revenue'], 'Completed status only; not payment data'];
        yield ['Overview', 'Average booked duration', '', '', '', $metrics['average_duration_days'], '', 'Confirmed bookings; stored duration snapshot'];
        yield ['Overview', 'Average daily rate snapshot', '', '', '', '', $metrics['average_daily_rate'], 'Confirmed bookings; stored rate snapshots'];
        yield ['Overview', 'Fleet utilization', '', '', '', '', $metrics['fleet_utilization'], 'Rented days divided by available fleet days'];

        foreach ($report['trend'] as $point) {
            yield ['Reservations trend', $point['label'], '', '', $point['count'], '', '', 'Bookings counted by pickup date'];
        }

        foreach ($report['status_breakdown'] as $status) {
            yield ['Status breakdown', $status['label'], '', '', $status['count'], '', '', ''];
        }

        foreach ($report['fleet']['vehicles'] as $vehicle) {
            yield [
                'Fleet utilization',
                $vehicle['name'],
                $vehicle['category'],
                $vehicle['vehicle_id'],
                $vehicle['booking_count'],
                $vehicle['rented_days'],
                $vehicle['utilization'],
                'Available '.$vehicle['available_days'].'; idle '.$vehicle['idle_days'].'; maintenance '.$vehicle['maintenance_days'].' days',
            ];
        }

        foreach ($report['popular_vehicles'] as $vehicle) {
            yield ['Popular vehicles', $vehicle->name, $vehicle->category, $vehicle->vehicle_id, $vehicle->booking_count, $vehicle->rented_days, '', 'Confirmed bookings; stored duration snapshots'];
        }

        foreach ($report['popular_categories'] as $category) {
            yield ['Popular categories', $category->category, '', '', $category->booking_count, $category->rented_days, '', 'Confirmed bookings; stored duration snapshots'];
        }

        yield ['Customer insights', 'New customer records', '', '', $report['customers']['new_records'], '', '', 'Customer record created during range; not account registrations'];
        yield ['Customer insights', 'Returning customers', '', '', $report['customers']['returning_customers'], '', '', 'Existing customer records with matching booking activity'];

        foreach ($report['customers']['top_customers'] as $customer) {
            yield ['Top customers', $customer->full_name, '', '', $customer->completed_bookings, '', '', 'Completed bookings; no contact data exported'];
        }

        yield ['Maintenance summary', 'Maintenance periods', '', '', $report['maintenance']['period_count'], '', '', 'Periods overlapping range'];
        yield ['Maintenance summary', 'Total maintenance days', '', '', '', $report['maintenance']['days'], '', 'Union of maintenance intervals, clipped to range'];
        yield ['Maintenance summary', 'Vehicles currently under maintenance', '', '', $report['maintenance']['vehicles_currently_under_maintenance'], '', '', 'Current availability status'];

        foreach ($report['fleet']['vehicles'] as $vehicle) {
            if ($vehicle['maintenance_days'] > 0) {
                yield ['Maintenance by vehicle', $vehicle['name'], $vehicle['category'], $vehicle['vehicle_id'], '', $vehicle['maintenance_days'], '', 'Days clipped to range'];
            }
        }

        foreach ($this->maintenanceQuery(
            $filters,
            $report['date_range']['start'],
            $report['date_range']['end']->copy()->addDay(),
        )->join('vehicles', 'vehicles.id', '=', 'vehicle_unavailabilities.vehicle_id')
            ->select([
                'vehicle_unavailabilities.vehicle_id',
                'vehicles.name as vehicle_name',
                'vehicles.category',
                'vehicle_unavailabilities.start_datetime',
                'vehicle_unavailabilities.end_datetime',
            ])
            ->orderBy('vehicle_unavailabilities.start_datetime')
            ->orderBy('vehicle_unavailabilities.id')
            ->cursor() as $period) {
            yield [
                'Maintenance periods',
                $period->vehicle_name,
                $period->category,
                $period->vehicle_id,
                '',
                '',
                $period->start_datetime,
                $period->end_datetime,
            ];
        }

        yield ['Rental operations', 'Completed rentals', '', '', $report['operations']['completed_rentals'], '', '', 'Completed status; counted by pickup date'];
        yield ['Rental operations', 'Overdue returns', '', '', $report['operations']['overdue_returns'], '', '', 'Ongoing bookings with scheduled return before export time'];
        yield ['Rental operations', 'Average actual rental length', '', '', $report['operations']['paired_audit_rentals'], $report['operations']['average_actual_rental_days'], '', 'Only completed rentals with paired check-out and check-in timestamps'];
    }

    public static function safeCsvCell(mixed $value): string
    {
        $cell = (string) $value;

        return preg_match('/^[=+\-@]/', $cell) === 1 ? "'".$cell : $cell;
    }

    private function overview(array $filters, Carbon $start, Carbon $endExclusive): array
    {
        $confirmedStatuses = self::CONFIRMED_STATUSES;
        $query = $this->reservationQuery($filters, $start, $endExclusive)
            ->selectRaw('COUNT(*) AS total_reservations')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS approved', [Booking::STATUS_APPROVED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS ongoing', [Booking::STATUS_ONGOING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [Booking::STATUS_COMPLETED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS rejected', [Booking::STATUS_REJECTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled', [Booking::STATUS_CANCELLED])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status IN (?, ?, ?) THEN COALESCE(estimated_total, daily_rate_snapshot * rental_duration_days, 0) ELSE 0 END), 0) AS estimated_revenue',
                $confirmedStatuses,
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN COALESCE(estimated_total, daily_rate_snapshot * rental_duration_days, 0) ELSE 0 END), 0) AS completed_estimated_revenue',
                [Booking::STATUS_COMPLETED],
            )
            ->selectRaw(
                'AVG(CASE WHEN status IN (?, ?, ?) THEN rental_duration_days END) AS average_duration_days',
                $confirmedStatuses,
            )
            ->selectRaw(
                'AVG(CASE WHEN status IN (?, ?, ?) THEN daily_rate_snapshot END) AS average_daily_rate',
                $confirmedStatuses,
            )
            ->first();

        $confirmedOutcomes = (int) $query->approved + (int) $query->ongoing + (int) $query->completed;
        $approvalDenominator = $confirmedOutcomes + (int) $query->rejected;

        return [
            'total_reservations' => (int) $query->total_reservations,
            'approved' => (int) $query->approved,
            'completed' => (int) $query->completed,
            'cancelled_rejected' => (int) $query->cancelled + (int) $query->rejected,
            'approval_rate' => $approvalDenominator > 0
                ? round(($confirmedOutcomes / $approvalDenominator) * 100, 1)
                : 0.0,
            'approval_denominator' => $approvalDenominator,
            'estimated_revenue' => (float) $query->estimated_revenue,
            'completed_estimated_revenue' => (float) $query->completed_estimated_revenue,
            'average_duration_days' => $query->average_duration_days === null
                ? null
                : (float) $query->average_duration_days,
            'average_daily_rate' => $query->average_daily_rate === null
                ? null
                : (float) $query->average_daily_rate,
            'fleet_utilization' => 0.0,
        ];
    }

    private function statusBreakdown(array $filters, Carbon $start, Carbon $endExclusive): Collection
    {
        $counts = $this->reservationQuery($filters, $start, $endExclusive)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(self::STATUSES)->map(fn (string $status): array => [
            'status' => $status,
            'label' => ucfirst($status),
            'count' => (int) ($counts->get($status) ?? 0),
        ]);
    }

    private function reservationDateCounts(array $filters, Carbon $start, Carbon $endExclusive): Collection
    {
        return $this->reservationQuery($filters, $start, $endExclusive)
            ->selectRaw('DATE(pickup_datetime) AS report_date, COUNT(*) AS aggregate')
            ->groupBy('report_date')
            ->pluck('aggregate', 'report_date');
    }

    private function reservationTrend(Collection $dateCounts, Carbon $start, Carbon $end, int $rangeDays): array
    {
        $grain = $rangeDays <= 31 ? 'day' : ($rangeDays <= 180 ? 'week' : 'month');
        $buckets = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $bucketStart = match ($grain) {
                'week' => $date->copy()->startOfWeek(),
                'month' => $date->copy()->startOfMonth(),
                default => $date->copy(),
            };
            $key = $bucketStart->toDateString();

            if (! isset($buckets[$key])) {
                $bucketEnd = match ($grain) {
                    'week' => $bucketStart->copy()->endOfWeek(),
                    'month' => $bucketStart->copy()->endOfMonth(),
                    default => $bucketStart->copy(),
                };
                $visibleStart = $bucketStart->lt($start) ? $start : $bucketStart;
                $visibleEnd = $bucketEnd->gt($end) ? $end : $bucketEnd;
                $label = $grain === 'month'
                    ? $bucketStart->format('M Y')
                    : ($grain === 'week'
                        ? $visibleStart->format('M j').'–'.$visibleEnd->format('j')
                        : $bucketStart->format('M j'));

                $buckets[$key] = [
                    'label' => $label,
                    'start' => $visibleStart->toDateString(),
                    'end' => $visibleEnd->toDateString(),
                    'count' => 0,
                ];
            }

            $buckets[$key]['count'] += (int) ($dateCounts->get($date->toDateString()) ?? 0);
        }

        $maximum = max(1, max(array_column($buckets, 'count') ?: [0]));

        return collect($buckets)->map(fn (array $bucket): array => [
            ...$bucket,
            'height' => $bucket['count'] > 0
                ? max(8, (int) round(($bucket['count'] / $maximum) * 100))
                : 0,
        ])->values()->all();
    }

    private function vehicleUtilization(
        array $filters,
        Carbon $start,
        Carbon $endExclusive,
        int $rangeDays,
        CarbonInterface $now,
    ): array {
        $vehicles = $this->vehicleQuery($filters)->orderBy('name')->get(['id', 'name', 'category', 'status']);
        $vehicleIds = $vehicles->pluck('id')->all();
        $availabilityStatuses = $vehicleIds === []
            ? []
            : $this->availability->vehicleAvailabilityStatusesAt($now, $vehicleIds);
        $bookedDays = $this->rentedDayTotals($filters, $start, $endExclusive)
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->vehicle_id);
        $maintenanceDays = $this->maintenanceDaysByVehicle($filters, $start, $endExclusive);

        $rows = $vehicles->map(function (Vehicle $vehicle) use (
            $availabilityStatuses,
            $bookedDays,
            $maintenanceDays,
            $rangeDays,
        ): array {
            $maintenance = min((float) $rangeDays, max(0, (float) ($maintenanceDays[$vehicle->id] ?? 0)));
            $available = $vehicle->status === Vehicle::STATUS_ACTIVE
                ? max(0, $rangeDays - $maintenance)
                : 0.0;
            $rawRented = max(0, (float) ($bookedDays->get((string) $vehicle->id)->rented_days ?? 0));
            $rented = min($available, $rawRented);

            return [
                'vehicle_id' => (string) $vehicle->id,
                'name' => $vehicle->name,
                'category' => $vehicle->category,
                'status' => $vehicle->status,
                'current_state' => $availabilityStatuses[$vehicle->id] ?? $vehicle->status,
                'booking_count' => (int) ($bookedDays->get((string) $vehicle->id)->booking_count ?? 0),
                'rented_days' => round($rented, 2),
                'available_days' => round($available, 2),
                'maintenance_days' => round($maintenance, 2),
                'idle_days' => round(max(0, $available - $rented), 2),
                'utilization' => $available > 0 ? round(min(100, ($rented / $available) * 100), 1) : 0.0,
            ];
        });

        $availableDays = (float) $rows->sum('available_days');
        $rentedDays = (float) $rows->sum('rented_days');

        return [
            $rows,
            [
                'available_days' => round($availableDays, 2),
                'rented_days' => round($rentedDays, 2),
                'idle_days' => round(max(0, $availableDays - $rentedDays), 2),
                'utilization' => $availableDays > 0
                    ? round(min(100, ($rentedDays / $availableDays) * 100), 1)
                    : 0.0,
            ],
            $maintenanceDays,
        ];
    }

    private function rentedDayTotals(array $filters, Carbon $start, Carbon $endExclusive): Builder
    {
        [$durationSql, $bindings] = $this->clippedDurationExpression(
            'pickup_datetime',
            'return_datetime',
            $start,
            $endExclusive,
        );

        return $this->overlappingBookingQuery($filters, $start, $endExclusive)
            ->select('vehicle_id')
            ->selectRaw('COUNT(*) AS booking_count')
            ->selectRaw("COALESCE(SUM({$durationSql}), 0) AS rented_days", $bindings)
            ->groupBy('vehicle_id');
    }

    private function clippedDurationExpression(
        string $startColumn,
        string $endColumn,
        Carbon $rangeStart,
        Carbon $rangeEnd,
    ): array {
        $startValue = $rangeStart->toDateTimeString();
        $endValue = $rangeEnd->toDateTimeString();
        $clippedStart = "CASE WHEN {$startColumn} < ? THEN ? ELSE {$startColumn} END";
        $clippedEnd = "CASE WHEN {$endColumn} > ? THEN ? ELSE {$endColumn} END";

        if (DB::connection()->getDriverName() === 'sqlite') {
            return [
                "(julianday({$clippedEnd}) - julianday({$clippedStart}))",
                [$endValue, $endValue, $startValue, $startValue],
            ];
        }

        return [
            "(TIMESTAMPDIFF(SECOND, {$clippedStart}, {$clippedEnd}) / 86400.0)",
            [$startValue, $startValue, $endValue, $endValue],
        ];
    }

    private function maintenanceDaysByVehicle(array $filters, Carbon $start, Carbon $endExclusive): array
    {
        $days = [];
        $currentVehicleId = null;
        $currentStart = null;
        $currentEnd = null;
        $flush = static function () use (&$days, &$currentVehicleId, &$currentStart, &$currentEnd): void {
            if ($currentVehicleId !== null && $currentStart !== null && $currentEnd !== null) {
                $days[$currentVehicleId] = ($days[$currentVehicleId] ?? 0)
                    + (($currentEnd->getTimestamp() - $currentStart->getTimestamp()) / 86400);
            }
        };

        $intervals = $this->maintenanceQuery($filters, $start, $endExclusive)
            ->select(['vehicle_id', 'start_datetime', 'end_datetime'])
            ->orderBy('vehicle_id')
            ->orderBy('start_datetime')
            ->cursor();

        foreach ($intervals as $interval) {
            $vehicleId = (string) $interval->vehicle_id;
            $intervalStart = $interval->start_datetime->lt($start) ? $start->copy() : $interval->start_datetime->copy();
            $intervalEnd = $interval->end_datetime->gt($endExclusive) ? $endExclusive->copy() : $interval->end_datetime->copy();

            if ($vehicleId !== $currentVehicleId) {
                $flush();
                $currentVehicleId = $vehicleId;
                $currentStart = $intervalStart;
                $currentEnd = $intervalEnd;

                continue;
            }

            if ($intervalStart->lte($currentEnd)) {
                if ($intervalEnd->gt($currentEnd)) {
                    $currentEnd = $intervalEnd;
                }

                continue;
            }

            $flush();
            $currentStart = $intervalStart;
            $currentEnd = $intervalEnd;
        }

        $flush();

        return $days;
    }

    private function popularVehicles(array $filters, Carbon $start, Carbon $endExclusive): Collection
    {
        return $this->confirmedPickupQuery($filters, $start, $endExclusive)
            ->join('vehicles', 'vehicles.id', '=', 'bookings.vehicle_id')
            ->selectRaw('vehicles.id AS vehicle_id, vehicles.name, vehicles.category')
            ->selectRaw('COUNT(bookings.id) AS booking_count')
            ->selectRaw('COALESCE(SUM(bookings.rental_duration_days), 0) AS rented_days')
            ->groupBy('vehicles.id', 'vehicles.name', 'vehicles.category')
            ->orderByDesc('booking_count')
            ->orderByDesc('rented_days')
            ->orderBy('vehicles.name')
            ->limit(10)
            ->get();
    }

    private function popularCategories(array $filters, Carbon $start, Carbon $endExclusive): Collection
    {
        return $this->confirmedPickupQuery($filters, $start, $endExclusive)
            ->join('vehicles', 'vehicles.id', '=', 'bookings.vehicle_id')
            ->selectRaw('vehicles.category')
            ->selectRaw('COUNT(bookings.id) AS booking_count')
            ->selectRaw('COALESCE(SUM(bookings.rental_duration_days), 0) AS rented_days')
            ->groupBy('vehicles.category')
            ->orderByDesc('booking_count')
            ->orderByDesc('rented_days')
            ->orderBy('vehicles.category')
            ->limit(10)
            ->get();
    }

    private function customerInsights(array $filters, Carbon $start, Carbon $endExclusive): array
    {
        $customerIds = $this->reservationQuery($filters, $start, $endExclusive)
            ->select('customer_id')
            ->distinct();
        $activity = Customer::query()
            ->whereIn('id', $customerIds)
            ->selectRaw('COUNT(*) AS total_customers')
            ->selectRaw(
                'SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS new_records',
                [$start->toDateTimeString(), $endExclusive->toDateTimeString()],
            )
            ->selectRaw('SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS returning_customers', [$start->toDateTimeString()])
            ->first();

        $topCustomers = $this->reservationQuery($filters, $start, $endExclusive)
            ->where('bookings.status', Booking::STATUS_COMPLETED)
            ->join('customers', 'customers.id', '=', 'bookings.customer_id')
            ->selectRaw('customers.id AS customer_id, customers.full_name')
            ->selectRaw('COUNT(bookings.id) AS completed_bookings')
            ->groupBy('customers.id', 'customers.full_name')
            ->orderByDesc('completed_bookings')
            ->orderBy('customers.full_name')
            ->limit(10)
            ->get();

        return [
            'total_customers' => (int) $activity->total_customers,
            'new_records' => (int) $activity->new_records,
            'returning_customers' => (int) $activity->returning_customers,
            'top_customers' => $topCustomers,
        ];
    }

    private function maintenanceQuery(array $filters, Carbon $start, Carbon $endExclusive): Builder
    {
        return VehicleUnavailability::query()
            ->where('start_datetime', '<', $endExclusive->toDateTimeString())
            ->where('end_datetime', '>', $start->toDateTimeString())
            ->when($filters['vehicle_id'] ?? null, fn (Builder $query, string $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereHas(
                'vehicle',
                fn (Builder $vehicles) => $vehicles->where('category', $category),
            ));
    }

    private function rentalOperations(array $filters, Carbon $start, Carbon $endExclusive, CarbonInterface $now): array
    {
        $bookings = $this->reservationQuery($filters, $start, $endExclusive);
        $completed = (clone $bookings)->where('status', Booking::STATUS_COMPLETED)->count();
        $overdue = (clone $bookings)
            ->where('status', Booking::STATUS_ONGOING)
            ->where('return_datetime', '<', $now->toDateTimeString())
            ->count();

        $completedBookingIds = (clone $bookings)
            ->where('status', Booking::STATUS_COMPLETED)
            ->select('id');
        $checkOutActions = ['rental_checked_out', 'booking_marked_ongoing'];
        $checkInActions = ['rental_checked_in', 'booking_marked_completed'];
        $pairedLogs = AuditLog::query()
            ->where('subject_type', Booking::class)
            ->whereIn('subject_id', $completedBookingIds)
            ->whereIn('action', [...$checkOutActions, ...$checkInActions])
            ->select('subject_id')
            ->selectRaw(
                'MIN(CASE WHEN action IN (?, ?) THEN created_at END) AS checked_out_at',
                $checkOutActions,
            )
            ->selectRaw(
                'MIN(CASE WHEN action IN (?, ?) THEN created_at END) AS checked_in_at',
                $checkInActions,
            )
            ->groupBy('subject_id');
        $completedIntervals = (clone $completedBookingIds)
            ->leftJoinSub($pairedLogs, 'rental_audit_pairs', fn ($join) => $join
                ->on('rental_audit_pairs.subject_id', '=', 'bookings.id'))
            ->selectRaw('COALESCE(bookings.checked_out_at, rental_audit_pairs.checked_out_at) AS checked_out_at')
            ->selectRaw('COALESCE(bookings.checked_in_at, rental_audit_pairs.checked_in_at) AS checked_in_at');
        $durationExpression = DB::connection()->getDriverName() === 'sqlite'
            ? '(julianday(checked_in_at) - julianday(checked_out_at))'
            : '(TIMESTAMPDIFF(SECOND, checked_out_at, checked_in_at) / 86400.0)';
        $actualDuration = DB::query()
            ->fromSub($completedIntervals, 'completed_intervals')
            ->whereNotNull('checked_out_at')
            ->whereNotNull('checked_in_at')
            ->whereColumn('checked_in_at', '>=', 'checked_out_at')
            ->selectRaw('COUNT(*) AS paired_rentals')
            ->selectRaw("AVG({$durationExpression}) AS average_rental_days")
            ->first();

        return [
            'completed_rentals' => $completed,
            'overdue_returns' => $overdue,
            'paired_audit_rentals' => (int) $actualDuration->paired_rentals,
            'average_actual_rental_days' => $actualDuration->average_rental_days === null
                ? null
                : (float) $actualDuration->average_rental_days,
        ];
    }

    private function vehicleOptions(array $filters): Collection
    {
        return $this->vehicleQuery(['category' => $filters['category'] ?? null])
            ->orderBy('name')
            ->get(['id', 'name', 'category']);
    }

    private function vehicleQuery(array $filters): Builder
    {
        return Vehicle::query()
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['vehicle_id'] ?? null, fn (Builder $query, string $vehicleId) => $query->whereKey($vehicleId));
    }

    private function reservationQuery(array $filters, Carbon $start, Carbon $endExclusive): Builder
    {
        return Booking::query()
            ->where('pickup_datetime', '>=', $start->toDateTimeString())
            ->where('pickup_datetime', '<', $endExclusive->toDateTimeString())
            ->when($filters['vehicle_id'] ?? null, fn (Builder $query, string $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereHas(
                'vehicle',
                fn (Builder $vehicles) => $vehicles->where('category', $category),
            ));
    }

    private function confirmedPickupQuery(array $filters, Carbon $start, Carbon $endExclusive): Builder
    {
        return $this->reservationQuery($filters, $start, $endExclusive)
            ->whereIn('bookings.status', self::CONFIRMED_STATUSES);
    }

    private function overlappingBookingQuery(array $filters, Carbon $start, Carbon $endExclusive): Builder
    {
        return Booking::query()
            ->whereIn('status', self::CONFIRMED_STATUSES)
            ->where('pickup_datetime', '<', $endExclusive->toDateTimeString())
            ->where('return_datetime', '>', $start->toDateTimeString())
            ->when($filters['vehicle_id'] ?? null, fn (Builder $query, string $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereHas(
                'vehicle',
                fn (Builder $vehicles) => $vehicles->where('category', $category),
            ));
    }
}
