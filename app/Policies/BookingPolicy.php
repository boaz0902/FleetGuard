<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return $user->isCustomer()
            && $user->hasVerifiedEmail()
            && $booking->customer()->exists()
            && $booking->customer->user_id === $user->id;
    }

    public function cancelByCustomer(User $user, Booking $booking): bool
    {
        return $user->isCustomer()
            && $user->hasVerifiedEmail()
            && $booking->customer()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function review(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $this->review($user);
    }

    public function manageRental(User $user, Booking $booking): bool
    {
        return $user->isAdmin();
    }
}
