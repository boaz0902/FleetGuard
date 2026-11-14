<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isStaff();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function addNote(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function manageStatus(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }
}
