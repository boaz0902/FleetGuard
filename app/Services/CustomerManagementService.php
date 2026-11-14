<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerManagementService
{
    public function metrics(CarbonInterface $now): array
    {
        $bookingMetrics = Booking::query()
            ->selectRaw('COUNT(DISTINCT CASE WHEN status = ? THEN customer_id END) AS with_pending', [Booking::STATUS_PENDING])
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN status = ? AND return_datetime > ? THEN customer_id END) AS with_upcoming',
                [Booking::STATUS_APPROVED, $now->toDateTimeString()],
            )
            ->selectRaw('COUNT(DISTINCT CASE WHEN status = ? THEN customer_id END) AS with_ongoing', [Booking::STATUS_ONGOING])
            ->selectRaw('COUNT(DISTINCT CASE WHEN status = ? THEN customer_id END) AS with_completed', [Booking::STATUS_COMPLETED])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS pending_bookings', [Booking::STATUS_PENDING])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS approved_bookings', [Booking::STATUS_APPROVED])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS ongoing_bookings', [Booking::STATUS_ONGOING])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS completed_bookings', [Booking::STATUS_COMPLETED])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS rejected_bookings', [Booking::STATUS_REJECTED])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS cancelled_bookings', [Booking::STATUS_CANCELLED])
            ->first();

        return [
            'total' => Customer::query()->count(),
            'pending' => (int) $bookingMetrics->with_pending,
            'upcoming' => (int) $bookingMetrics->with_upcoming,
            'ongoing' => (int) $bookingMetrics->with_ongoing,
            'completed' => (int) $bookingMetrics->with_completed,
            'new_this_month' => Customer::query()
                ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->count(),
            'without_bookings' => Customer::query()->doesntHave('bookings')->count(),
            'booking_summary' => [
                'pending' => (int) $bookingMetrics->pending_bookings,
                'approved' => (int) $bookingMetrics->approved_bookings,
                'ongoing' => (int) $bookingMetrics->ongoing_bookings,
                'completed' => (int) $bookingMetrics->completed_bookings,
                'rejected' => (int) $bookingMetrics->rejected_bookings,
                'cancelled' => (int) $bookingMetrics->cancelled_bookings,
            ],
        ];
    }

    public function directory(array $filters, CarbonInterface $now, bool $showContact): LengthAwarePaginator
    {
        $query = Customer::query()
            ->select('customers.*')
            ->withCount(['bookings as total_bookings'])
            ->selectSub(
                Booking::query()
                    ->select('created_at')
                    ->whereColumn('bookings.customer_id', 'customers.id')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(1),
                'latest_booking_at',
            )
            ->selectSub(
                Booking::query()
                    ->select('status')
                    ->whereColumn('bookings.customer_id', 'customers.id')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(1),
                'latest_booking_status',
            );

        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $pattern = "%{$search}%";
            $query->where(function (Builder $customerQuery) use ($pattern): void {
                $customerQuery
                    ->where('full_name', 'like', $pattern)
                    ->orWhere('email', 'like', $pattern)
                    ->orWhere('mobile_number', 'like', $pattern);
            });
        }

        match ($filters['activity'] ?? null) {
            'none' => $query->doesntHave('bookings'),
            'pending' => $query->whereHas('bookings', fn (Builder $bookings) => $bookings
                ->where('status', Booking::STATUS_PENDING)),
            'upcoming' => $query->whereHas('bookings', fn (Builder $bookings) => $bookings
                ->where('status', Booking::STATUS_APPROVED)
                ->where('return_datetime', '>', $now->toDateTimeString())),
            'ongoing' => $query->whereHas('bookings', fn (Builder $bookings) => $bookings
                ->where('status', Booking::STATUS_ONGOING)),
            'completed' => $query->whereHas('bookings', fn (Builder $bookings) => $bookings
                ->where('status', Booking::STATUS_COMPLETED)),
            default => null,
        };

        if (($filters['joined'] ?? null) === 'month') {
            $query->whereBetween('customers.created_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ]);
        }

        $sortColumns = [
            'full_name' => 'customers.full_name',
            'email' => 'customers.email',
            'total_bookings' => 'total_bookings',
            'latest_booking_at' => 'latest_booking_at',
            'created_at' => 'customers.created_at',
        ];
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $customers = $query
            ->orderBy($sortColumns[$sort], $direction)
            ->orderBy('customers.id', $direction)
            ->paginate(10)
            ->withQueryString();

        $customers->getCollection()->each(function (Customer $customer) use ($showContact): void {
            $customer->setAttribute('display_email', $this->emailForDisplay($customer, $showContact));
            $customer->setAttribute('display_phone', $this->phoneForDisplay($customer, $showContact));
        });

        return $customers;
    }

    public function details(
        Customer $customer,
        CarbonInterface $now,
        bool $includeAudit,
        bool $includeBookings,
    ): array {
        $bookingDetails = ['booking_count' => 0, 'lifetime_total' => 0.0, 'current_and_upcoming' => collect(), 'bookings' => null];

        if ($includeBookings) {
            $totals = $customer->bookings()
                ->selectRaw('COUNT(*) AS booking_count, COALESCE(SUM(estimated_total), 0) AS lifetime_total')
                ->first();

            $bookingDetails = [
                'booking_count' => (int) $totals->booking_count,
                'lifetime_total' => (float) $totals->lifetime_total,
                'current_and_upcoming' => $customer->bookings()
                    ->with('vehicle:id,name')
                    ->where(function (Builder $bookings) use ($now): void {
                        $bookings
                            ->where('status', Booking::STATUS_ONGOING)
                            ->orWhere(function (Builder $upcoming) use ($now): void {
                                $upcoming
                                    ->where('status', Booking::STATUS_APPROVED)
                                    ->where('return_datetime', '>', $now->toDateTimeString());
                            });
                    })
                    ->orderBy('pickup_datetime')
                    ->orderBy('id')
                    ->get(),
                'bookings' => $customer->bookings()
                    ->with('vehicle:id,name')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->paginate(10)
                    ->withQueryString(),
            ];
        }

        $activity = $includeAudit
            ? AuditLog::query()
                ->with('user:id,name')
                ->where(function (Builder $logs) use ($customer): void {
                    $logs
                        ->where(function (Builder $customerLogs) use ($customer): void {
                            $customerLogs
                                ->where('subject_type', Customer::class)
                                ->where('subject_id', (string) $customer->getKey());
                        })
                        ->orWhere(function (Builder $bookingLogs) use ($customer): void {
                            $bookingLogs
                                ->where('subject_type', Booking::class)
                                ->whereIn(
                                    'subject_id',
                                    Booking::query()
                                        ->select('id')
                                        ->where('customer_id', $customer->getKey()),
                                );
                        });
                })
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
            : new Collection;

        return [
            ...$bookingDetails,
            'activity' => $activity,
        ];
    }

    public function updateProfile(Customer $customer, array $attributes, User $actor): bool
    {
        return DB::transaction(function () use ($customer, $attributes, $actor): bool {
            $record = Customer::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $attributes = array_intersect_key($attributes, array_flip(['full_name', 'email', 'mobile_number']));
            $changes = [];

            foreach ($attributes as $field => $newValue) {
                $oldValue = $record->{$field};

                if ($oldValue !== $newValue) {
                    $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
                }
            }

            if ($changes === []) {
                return false;
            }

            $record->forceFill(array_intersect_key($attributes, array_flip(array_keys($changes))))->save();

            AuditLog::query()->create([
                'user_id' => $actor->getKey(),
                'action' => 'customer_updated',
                'subject_type' => Customer::class,
                'subject_id' => (string) $record->getKey(),
                'changes' => $changes,
                'created_at' => now(),
            ]);

            return true;
        });
    }

    public function emailForDisplay(Customer $customer, bool $showContact): string
    {
        if ($showContact) {
            return $customer->email;
        }

        [$localPart, $domain] = array_pad(explode('@', $customer->email, 2), 2, '');

        return $domain === '' ? 'Hidden' : substr($localPart, 0, 1).'***@'.$domain;
    }

    public function phoneForDisplay(Customer $customer, bool $showContact): string
    {
        if ($showContact) {
            return $customer->mobile_number;
        }

        $digits = preg_replace('/\D+/', '', $customer->mobile_number) ?? '';

        return $digits === '' ? 'Hidden' : '•••• '.substr($digits, -4);
    }
}
