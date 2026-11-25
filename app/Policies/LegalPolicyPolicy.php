<?php

namespace App\Policies;

use App\Models\LegalPolicy;
use App\Models\User;

class LegalPolicyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, LegalPolicy $legalPolicy): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LegalPolicy $legalPolicy): bool
    {
        return $user->isAdmin();
    }
}
