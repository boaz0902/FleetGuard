<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerBookingService
{
    public const FILTERS = [
        'all',
        Booking::STATUS_PENDING,
        Booking::STATUS_APPROVED,
        Booking::STATUS_ONGOING,
        Booking::STATUS_COMPLETED,
        'closed',
    ];

    public function summary(User $user, CarbonInterface $now): array
    {
        if (! $user->hasVerifiedEmail()) {
            return [
                'upcoming' => 0,
                'active' => 0,
                'completed' => 0,
                'next_booking' => null,
            ];
        }

        $base = $this->forUser($user);

        return [
            'upcoming' => (clone $base)
                ->where('status', Booking::STATUS_APPROVED)
                ->where('pickup_datetime', '>=', $now)
                ->count(),
            'active' => (clone $base)->where('status', Booking::STATUS_ONGOING)->count(),
            'completed' => (clone $base)->where('status', Booking::STATUS_COMPLETED)->count(),
            'next_booking' => (clone $base)
                ->with('vehicle')
                ->where('status', Booking::STATUS_APPROVED)
                ->where('pickup_datetime', '>=', $now)
                ->orderBy('pickup_datetime')
                ->orderBy('id')
                ->first(),
        ];
    }

    public function statusCounts(User $user): Collection
    {
        return $this->forUser($user)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
    }

    public function forUser(User $user): Builder
    {
        return Booking::query()->whereHas(
            'customer',
            fn (Builder $query) => $query->where('user_id', $user->id),
        );
    }
}
