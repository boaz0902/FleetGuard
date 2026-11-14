<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RentalManagementService
{
    public function __construct(
        private readonly VehicleAvailabilityService $availability,
        private readonly CustomerManagementService $customers,
    ) {}

    public function metrics(CarbonInterface $now): array
    {
        $todayEnd = $now->copy()->endOfDay();
        $counts = Booking::query()
            ->selectRaw(
                'COUNT(CASE WHEN status = ? AND pickup_datetime <= ? THEN 1 END) AS ready',
                [Booking::STATUS_APPROVED, $now->toDateTimeString()],
            )
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS active', [Booking::STATUS_ONGOING])
            ->selectRaw(
                'COUNT(CASE WHEN status = ? AND return_datetime >= ? AND return_datetime <= ? THEN 1 END) AS due_today',
                [Booking::STATUS_ONGOING, $now->toDateTimeString(), $todayEnd->toDateTimeString()],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status = ? AND return_datetime < ? THEN 1 END) AS overdue',
                [Booking::STATUS_ONGOING, $now->toDateTimeString()],
            )
            ->selectRaw(
                'COUNT(CASE WHEN status IN (?, ?, ?) THEN 1 END) AS handled',
                [Booking::STATUS_APPROVED, Booking::STATUS_ONGOING, Booking::STATUS_COMPLETED],
            )
            ->first();

        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $checkInLogs = AuditLog::query()
            ->where('subject_type', Booking::class)
            ->whereIn('action', ['rental_checked_in', 'booking_marked_completed'])
            ->select('subject_id')
            ->selectRaw('MIN(created_at) AS audit_checked_in_at')
            ->groupBy('subject_id');
        $completedThisMonth = Booking::query()
            ->leftJoinSub($checkInLogs, 'checkin_audit', fn ($join) => $join
                ->on('checkin_audit.subject_id', '=', 'bookings.id'))
            ->where('bookings.status', Booking::STATUS_COMPLETED)
            ->whereBetween(
                DB::raw('COALESCE(bookings.checked_in_at, checkin_audit.audit_checked_in_at)'),
                [$monthStart->toDateTimeString(), $monthEnd->toDateTimeString()],
            )
            ->distinct()
            ->count('bookings.id');

        return [
            'ready' => (int) $counts->ready,
            'active' => (int) $counts->active,
            'due_today' => (int) $counts->due_today,
            'overdue' => (int) $counts->overdue,
            'completed_month' => $completedThisMonth,
            'handled' => (int) $counts->handled,
            'summary' => [
                'ready' => (int) $counts->ready,
                'active' => (int) $counts->active,
                'completed_month' => $completedThisMonth,
            ],
        ];
    }

    public function directory(array $filters, CarbonInterface $now): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with([
                'customer',
                'vehicle',
                'auditLogs' => fn ($logs) => $logs
                    ->whereIn('action', [
                        'rental_checked_out',
                        'rental_checked_in',
                        'booking_marked_ongoing',
                        'booking_marked_completed',
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id'),
            ])
            ->whereIn('status', [
                Booking::STATUS_APPROVED,
                Booking::STATUS_ONGOING,
                Booking::STATUS_COMPLETED,
            ]);

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $pattern = "%{$search}%";
            $query->where(function (Builder $bookings) use ($pattern): void {
                $bookings
                    ->where('booking_reference', 'like', $pattern)
                    ->orWhereHas('customer', fn (Builder $customers) => $customers
                        ->where('full_name', 'like', $pattern))
                    ->orWhereHas('vehicle', fn (Builder $vehicles) => $vehicles
                        ->where('name', 'like', $pattern));
            });
        }

        $query
            ->when($filters['pickup_from'] ?? null, fn (Builder $bookings, string $date) => $bookings
                ->whereDate('pickup_datetime', '>=', $date))
            ->when($filters['pickup_to'] ?? null, fn (Builder $bookings, string $date) => $bookings
                ->whereDate('pickup_datetime', '<=', $date));

        match ($filters['status'] ?? null) {
            'ready' => $query
                ->where('status', Booking::STATUS_APPROVED)
                ->where('pickup_datetime', '<=', $now->toDateTimeString()),
            'scheduled' => $query
                ->where('status', Booking::STATUS_APPROVED)
                ->where('pickup_datetime', '>', $now->toDateTimeString()),
            'ongoing' => $query->where('status', Booking::STATUS_ONGOING),
            'due_today' => $query
                ->where('status', Booking::STATUS_ONGOING)
                ->whereBetween('return_datetime', [$now->toDateTimeString(), $now->copy()->endOfDay()->toDateTimeString()]),
            'overdue' => $query
                ->where('status', Booking::STATUS_ONGOING)
                ->where('return_datetime', '<', $now->toDateTimeString()),
            'completed' => $query->where('status', Booking::STATUS_COMPLETED),
            default => null,
        };

        $sortColumns = [
            'booking_reference' => 'bookings.booking_reference',
            'pickup_datetime' => 'bookings.pickup_datetime',
            'return_datetime' => 'bookings.return_datetime',
            'status' => 'bookings.status',
            'created_at' => 'bookings.created_at',
        ];
        $sort = $filters['sort'] ?? 'pickup_datetime';
        $direction = $filters['direction'] ?? 'asc';

        $query
            ->when($sort === 'customer', fn (Builder $bookings) => $bookings->orderBy(
                Customer::query()->select('full_name')->whereColumn('customers.id', 'bookings.customer_id'),
                $direction,
            ))
            ->when($sort === 'vehicle', fn (Builder $bookings) => $bookings->orderBy(
                Vehicle::query()->select('name')->whereColumn('vehicles.id', 'bookings.vehicle_id'),
                $direction,
            ));

        if (isset($sortColumns[$sort])) {
            $query->orderBy($sortColumns[$sort], $direction);
        }

        return $query
            ->orderBy('bookings.id', $direction)
            ->paginate(10)
            ->withQueryString()
            ->through(function (Booking $booking) use ($now): Booking {
                $booking->setAttribute('rental_status', $this->statusFor($booking, $now));
                $checkedOut = $booking->auditLogs->first(fn (AuditLog $log) => in_array(
                    $log->action,
                    ['rental_checked_out', 'booking_marked_ongoing'],
                    true,
                ));
                $checkedIn = $booking->auditLogs->first(fn (AuditLog $log) => in_array(
                    $log->action,
                    ['rental_checked_in', 'booking_marked_completed'],
                    true,
                ));
                $checkedOutAt = $booking->checked_out_at ?? $checkedOut?->created_at;
                $checkedInAt = $booking->checked_in_at ?? $checkedIn?->created_at;
                $lateReturn = $checkedInAt?->greaterThan($booking->return_datetime) ?? false;
                $booking->setAttribute('effective_checked_out_at', $checkedOutAt);
                $booking->setAttribute('effective_checked_in_at', $checkedInAt);
                $booking->setAttribute('late_return', $lateReturn);

                return $booking;
            });
    }

    public function details(Booking $booking, CarbonInterface $now, bool $showContact): array
    {
        abort_unless(in_array($booking->status, [
            Booking::STATUS_APPROVED,
            Booking::STATUS_ONGOING,
            Booking::STATUS_COMPLETED,
        ], true), 404);

        $booking->load([
            'customer',
            'vehicle',
            'auditLogs' => fn ($query) => $query
                ->with('user:id,name')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        if ($booking->customer) {
            $booking->customer->setAttribute(
                'display_email',
                $this->customers->emailForDisplay($booking->customer, $showContact),
            );
            $booking->customer->setAttribute(
                'display_phone',
                $this->customers->phoneForDisplay($booking->customer, $showContact),
            );
        }

        $checkedOut = $booking->auditLogs->first(fn (AuditLog $log) => in_array(
            $log->action,
            ['rental_checked_out', 'booking_marked_ongoing'],
            true,
        ));
        $checkedIn = $booking->auditLogs->first(fn (AuditLog $log) => in_array(
            $log->action,
            ['rental_checked_in', 'booking_marked_completed'],
            true,
        ));
        $checkedOutAt = $booking->checked_out_at ?? $checkedOut?->created_at;
        $checkedInAt = $booking->checked_in_at ?? $checkedIn?->created_at;
        $lateReturn = $checkedInAt?->greaterThan($booking->return_datetime) ?? false;

        $timeline = $booking->auditLogs->map(function (AuditLog $log) use ($booking): array {
            $labels = [
                'booking_submitted' => 'Reservation requested',
                'booking_approved' => 'Reservation approved',
                'rental_checked_out' => 'Vehicle checked out',
                'rental_checked_in' => 'Vehicle checked in',
                'booking_marked_ongoing' => 'Rental marked ongoing',
                'booking_marked_completed' => 'Rental marked completed',
            ];
            $isCheckOut = in_array($log->action, ['rental_checked_out', 'booking_marked_ongoing'], true);
            $isCheckIn = in_array($log->action, ['rental_checked_in', 'booking_marked_completed'], true);
            $createdAt = $isCheckOut
                ? ($booking->checked_out_at ?? $log->created_at)
                : ($isCheckIn ? ($booking->checked_in_at ?? $log->created_at) : $log->created_at);
            $changes = $log->changes ?? [];

            if ($isCheckIn) {
                $changes['overdue'] = ($booking->checked_in_at ?? $log->created_at)
                    ->greaterThan($booking->return_datetime);
            }

            return [
                'label' => $labels[$log->action] ?? ucfirst(str_replace('_', ' ', $log->action)),
                'actor' => $log->user?->name ?? ($log->action === 'booking_submitted' ? 'Customer / guest' : 'System'),
                'created_at' => $createdAt,
                'changes' => $changes,
                'icon' => $isCheckOut ? 'key' : ($isCheckIn ? 'box-arrow-in-left' : 'circle'),
            ];
        })->values();

        if ($checkedOutAt !== null && $checkedOut === null) {
            $timeline->push([
                'label' => 'Vehicle checked out',
                'actor' => 'Rental record',
                'created_at' => $checkedOutAt,
                'changes' => [],
                'icon' => 'key',
            ]);
        }

        if ($checkedInAt !== null && $checkedIn === null) {
            $timeline->push([
                'label' => 'Vehicle checked in',
                'actor' => 'Rental record',
                'created_at' => $checkedInAt,
                'changes' => ['overdue' => $lateReturn],
                'icon' => 'box-arrow-in-left',
            ]);
        }

        $timeline = $timeline->sortBy(fn (array $entry) => $entry['created_at']?->getTimestamp())->values();

        $now = $now->copy();

        return [
            'booking' => $booking,
            'rental_status' => $this->statusFor($booking, $now),
            'checkout_block_reason' => $this->checkOutBlockReason($booking, $now),
            'timeline' => $timeline,
            'checked_out_at' => $checkedOutAt,
            'checked_in_at' => $checkedInAt,
            'late_return' => $lateReturn,
            'return_comparison' => $this->returnComparison($checkedInAt, $booking->return_datetime),
        ];
    }

    private function returnComparison(?CarbonInterface $checkedInAt, CarbonInterface $scheduledReturn): ?string
    {
        if ($checkedInAt === null) {
            return null;
        }

        $difference = $checkedInAt->getTimestamp() - $scheduledReturn->getTimestamp();

        if ($difference === 0) {
            return 'Returned on schedule';
        }

        $seconds = abs($difference);
        $parts = [];

        foreach ([86400 => 'day', 3600 => 'hour', 60 => 'minute', 1 => 'second'] as $unitSeconds => $unit) {
            $quantity = intdiv($seconds, $unitSeconds);

            if ($quantity > 0) {
                $parts[] = number_format($quantity).' '.Str::plural($unit, $quantity);
                $seconds %= $unitSeconds;
            }

            if (count($parts) === 2) {
                break;
            }
        }

        return 'Returned '.implode(' ', $parts).($difference > 0 ? ' late' : ' early');
    }

    public function checkOutBlockReason(Booking $booking, CarbonInterface $now): ?string
    {
        if ($booking->status !== Booking::STATUS_APPROVED) {
            return 'Only approved bookings can be checked out.';
        }

        if ($booking->pickup_datetime->greaterThan($now)) {
            return 'Check-out is available when the scheduled pickup time begins.';
        }

        $vehicle = $booking->vehicle;
        if (! $vehicle) {
            return 'The vehicle record is unavailable.';
        }

        if ($vehicle->status !== Vehicle::STATUS_ACTIVE) {
            return $vehicle->status === Vehicle::STATUS_MAINTENANCE
                ? 'This vehicle is under maintenance and cannot be checked out.'
                : 'This vehicle is inactive and cannot be checked out.';
        }

        if ($vehicle->unavailabilities()
            ->where('start_datetime', '<', $booking->return_datetime)
            ->where('end_datetime', '>', $booking->pickup_datetime)
            ->exists()) {
            return 'This vehicle has a maintenance period overlapping the rental.';
        }

        if ($this->availability->hasBookingConflict(
            $vehicle,
            $booking->pickup_datetime,
            $booking->return_datetime,
            $booking->id,
        )) {
            return 'Another approved or ongoing booking conflicts with this rental period.';
        }

        return $this->availability->isAvailable(
            $vehicle,
            $booking->pickup_datetime,
            $booking->return_datetime,
            $booking->id,
        ) ? null : 'The vehicle is not available for the full rental period.';
    }

    public function statusFor(Booking $booking, CarbonInterface $now): string
    {
        if ($booking->status === Booking::STATUS_APPROVED) {
            return $booking->pickup_datetime->lessThanOrEqualTo($now) ? 'ready' : 'scheduled';
        }

        if ($booking->status === Booking::STATUS_ONGOING) {
            if ($booking->return_datetime->lessThan($now)) {
                return 'overdue';
            }

            return $booking->return_datetime->isSameDay($now) ? 'due_today' : 'ongoing';
        }

        return 'completed';
    }
}
