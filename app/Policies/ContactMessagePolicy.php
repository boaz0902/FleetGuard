<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $this->viewAny($user);
    }

    public function updateStatus(User $user, ContactMessage $contactMessage): bool
    {
        return $user->isAdmin() || $user->isManager();
    }
}
