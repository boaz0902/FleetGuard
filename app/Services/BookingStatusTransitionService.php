<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingStatusTransitionService
{
    public function __construct(private readonly VehicleAvailabilityService $availability) {}

    public function transition(Booking $booking, string $status, User $actor, string $reason = ''): Booking
    {
        return DB::transaction(function () use ($booking, $status, $actor, $reason): Booking {
            $vehicle = Vehicle::query()
                ->whereKey($booking->vehicle_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $now = Carbon::now(config('app.timezone'));

            if (! $lockedBooking->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => ['This status transition is not allowed.'],
                ]);
            }

            if (
                in_array($status, [Booking::STATUS_ONGOING, Booking::STATUS_COMPLETED], true)
                && ! $actor->isAdmin()
            ) {
                abort(403);
            }

            if ($status === Booking::STATUS_APPROVED) {
                if (! $this->availability->isAvailable(
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

            if ($status === Booking::STATUS_ONGOING) {
                $this->assertCanCheckOut($lockedBooking, $vehicle, $now);
            }

            if ($status === Booking::STATUS_CANCELLED && $lockedBooking->status === Booking::STATUS_ONGOING) {
                throw ValidationException::withMessages([
                    'status' => ['An active rental must be checked in before it can be closed.'],
                ]);
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
                Booking::STATUS_ONGOING => 'rental_checked_out',
                Booking::STATUS_COMPLETED => 'rental_checked_in',
            };
            $changes = [
                'from' => $previousStatus,
                'to' => $status,
            ];

            if (in_array($status, [Booking::STATUS_ONGOING, Booking::STATUS_COMPLETED], true)) {
                $changes['vehicle_id'] = $vehicle->id;
            }

            if ($reason !== '') {
                $changes['reason'] = $reason;
            }

            if ($status === Booking::STATUS_ONGOING) {
                $changes['checked_out_at'] = $now->toIso8601String();
            } elseif ($status === Booking::STATUS_COMPLETED) {
                $changes['checked_in_at'] = $now->toIso8601String();
                $changes['overdue'] = $lockedBooking->return_datetime->lessThan($now);
            }

            AuditLog::query()->create([
                'user_id' => $actor->id,
                'action' => $action,
                'subject_type' => Booking::class,
                'subject_id' => $lockedBooking->id,
                'changes' => $changes,
                'created_at' => $now,
            ]);

            return $lockedBooking;
        });
    }

    private function assertCanCheckOut(Booking $booking, Vehicle $vehicle, Carbon $now): void
    {
        if ($booking->pickup_datetime->greaterThan($now)) {
            throw ValidationException::withMessages([
                'status' => ['Check-out is available when the scheduled pickup time begins.'],
            ]);
        }

        if ($vehicle->status !== Vehicle::STATUS_ACTIVE) {
            $message = $vehicle->status === Vehicle::STATUS_MAINTENANCE
                ? 'This vehicle is under maintenance and cannot be checked out.'
                : 'This vehicle is inactive and cannot be checked out.';

            throw ValidationException::withMessages(['status' => [$message]]);
        }

        if ($vehicle->unavailabilities()
            ->where('start_datetime', '<', $booking->return_datetime)
            ->where('end_datetime', '>', $booking->pickup_datetime)
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => ['This vehicle has a maintenance period overlapping the rental.'],
            ]);
        }

        if ($this->availability->hasBookingConflict(
            $vehicle,
            $booking->pickup_datetime,
            $booking->return_datetime,
            $booking->id,
        )) {
            throw ValidationException::withMessages([
                'status' => ['Another approved or ongoing booking conflicts with this rental period.'],
            ]);
        }

        if (! $this->availability->isAvailable(
            $vehicle,
            $booking->pickup_datetime,
            $booking->return_datetime,
            $booking->id,
        )) {
            throw ValidationException::withMessages([
                'status' => ['The vehicle is not available for the full rental period.'],
            ]);
        }
    }
}
