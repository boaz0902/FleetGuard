<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\VehicleAvailabilityService;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(VehicleAvailabilityService $availability): View
    {
        $now = Carbon::now('Asia/Manila');
        $fleetStatus = $availability->fleetStatusAt($now);
        $totalVehicles = $fleetStatus['total'];
        unset($fleetStatus['total']);

        $bookingStatusCounts = Booking::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $today = Carbon::now('Asia/Manila')->startOfDay();
        $activityStart = $today->copy()->subDays(6);
        $activityCounts = Booking::query()
            ->whereBetween('created_at', [$activityStart, $today->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as reservation_count')
            ->groupBy('activity_date')
            ->pluck('reservation_count', 'activity_date');

        $activityDays = collect(range(0, 6))->map(function (int $offset) use ($activityStart, $activityCounts): array {
            $date = $activityStart->copy()->addDays($offset);

            return [
                'date' => $date,
                'count' => (int) ($activityCounts->get($date->toDateString()) ?? 0),
            ];
        });
        $maxActivity = max(1, (int) $activityDays->max('count'));
        $activityChart = $activityDays->map(fn (array $day): array => [
            'date' => $day['date'],
            'count' => $day['count'],
            'height' => $day['count'] > 0
                ? max(8, (int) round(($day['count'] / $maxActivity) * 100))
                : 0,
        ]);

        $metrics = [
            'total_vehicles' => $totalVehicles,
            'available_vehicles' => $fleetStatus['available'],
            'pending_reservations' => (int) ($bookingStatusCounts->get(Booking::STATUS_PENDING) ?? 0),
            'active_rentals' => (int) ($bookingStatusCounts->get(Booking::STATUS_ONGOING) ?? 0),
            'total_reservations' => (int) $bookingStatusCounts->sum(),
        ];

        $recentReservations = Booking::query()
            ->with(['customer:id,full_name', 'vehicle:id,name'])
            ->latest('created_at')
            ->limit(6)
            ->get();

        return view('admin.dashboard', [
            'activityChart' => $activityChart,
            'activityHasData' => $activityDays->sum('count') > 0,
            'fleetStatus' => $fleetStatus,
            'metrics' => $metrics,
            'recentReservations' => $recentReservations,
            'today' => $today,
            'user' => request()->user(),
        ]);
    }
}
